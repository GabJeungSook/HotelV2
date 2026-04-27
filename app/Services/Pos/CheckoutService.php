<?php

namespace App\Services\Pos;

use App\Models\PosOrder;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single entry point for POS checkout. Wraps PosOrder header creation,
 * per-line Transaction snapshots, and stock decrement in one DB transaction.
 *
 * The Livewire UI just collects state and calls checkout() — all the
 * money / stock invariants live here so they can be unit-tested without
 * a browser.
 */
class CheckoutService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array  $cart  Each item: ['menu_id'=>int, 'name'=>string, 'unit_price'=>int, 'quantity'=>float]
     * @param  array  $context  Required: branch_id, user_id. Optional: shift_log_id, guest_id, room_id,
     *                          floor_id, discount_amount, discount_reason, paid_amount, change_amount,
     *                          source_type (default frontdesk), assigned_frontdesk, description.
     *
     * @throws InvalidArgumentException on cart/discount/payment validation failure
     * @throws InsufficientStockException when any line cannot be fulfilled
     */
    public function checkout(array $cart, array $context): PosOrder
    {
        if (empty($cart)) {
            throw new InvalidArgumentException('Cart is empty');
        }

        $branchId = $context['branch_id'] ?? throw new InvalidArgumentException('branch_id required');
        $userId   = $context['user_id']   ?? throw new InvalidArgumentException('user_id required');

        $shiftLogId        = $context['shift_log_id']        ?? null;
        $guestId           = $context['guest_id']            ?? null;
        $roomId            = $context['room_id']             ?? null;
        $floorId           = $context['floor_id']            ?? null;
        $checkinDetailId   = $context['checkin_detail_id']   ?? null;
        $discountAmount    = (int) ($context['discount_amount'] ?? 0);
        $discountReason    = $context['discount_reason']     ?? null;
        $paidAmount        = (int) ($context['paid_amount']  ?? 0);
        $changeAmount      = (int) ($context['change_amount'] ?? 0);
        $sourceType        = $context['source_type']         ?? StockMovement::SOURCE_FRONTDESK;
        $assignedFrontdesk = $context['assigned_frontdesk']  ?? null;
        $description       = $context['description']         ?? 'Food and Beverages';

        // Validate every line and compute subtotal in one pass.
        $subtotal = 0;
        foreach ($cart as $line) {
            foreach (['menu_id', 'name', 'unit_price', 'quantity'] as $required) {
                if (!array_key_exists($required, $line)) {
                    throw new InvalidArgumentException("Cart line missing '{$required}'");
                }
            }
            if ((float) $line['quantity'] <= 0) {
                throw new InvalidArgumentException('Cart line quantity must be > 0');
            }
            if ((int) $line['unit_price'] < 0) {
                throw new InvalidArgumentException('Cart line unit_price must be >= 0');
            }
            $subtotal += (int) round((int) $line['unit_price'] * (float) $line['quantity']);
        }

        if ($discountAmount < 0) {
            throw new InvalidArgumentException('Discount cannot be negative');
        }
        if ($discountAmount > $subtotal) {
            throw new InvalidArgumentException("Discount ({$discountAmount}) cannot exceed subtotal ({$subtotal})");
        }
        $total = $subtotal - $discountAmount;

        // Room-charge sales (guest_id present) defer payment to guest checkout —
        // payment_method stays NULL and no cash is collected at the register.
        // Walk-in cash sales must collect at least the total.
        $isRoomCharge = $guestId !== null;
        $paymentMethod = $isRoomCharge ? null : 'cash';

        if (!$isRoomCharge && $paidAmount < $total) {
            throw new InvalidArgumentException("Paid amount ({$paidAmount}) is less than total ({$total})");
        }

        return DB::transaction(function () use (
            $cart, $branchId, $userId, $shiftLogId, $guestId, $roomId, $floorId,
            $checkinDetailId, $discountAmount, $discountReason, $subtotal, $total,
            $paymentMethod, $paidAmount, $changeAmount, $sourceType, $assignedFrontdesk, $description
        ) {
            $order = PosOrder::create([
                'branch_id'         => $branchId,
                'user_id'           => $userId,
                'shift_log_id'      => $shiftLogId,
                'guest_id'          => $guestId,
                'room_id'           => $roomId,
                'payment_method'    => $paymentMethod,
                'subtotal'          => $subtotal,
                'discount_amount'   => $discountAmount,
                'discount_reason'   => $discountReason,
                'total'             => $total,
                'paid_amount'       => $paymentMethod === null ? 0 : $paidAmount,
                'change_amount'     => $paymentMethod === null ? 0 : $changeAmount,
            ]);

            foreach ($cart as $line) {
                $lineTotal = (int) round((int) $line['unit_price'] * (float) $line['quantity']);

                $transaction = Transaction::create([
                    'order_id'              => $order->id,
                    'branch_id'             => $branchId,
                    'room_id'               => $roomId,
                    'guest_id'              => $guestId,
                    'floor_id'              => $floorId,
                    'checkin_detail_id'     => $checkinDetailId,
                    'shift_log_id'          => $shiftLogId,
                    'transaction_type_id'   => 9,
                    // transactions.assigned_frontdesk_id is NOT NULL in the legacy schema.
                    // Match the kitchen/pub convention of always json_encoding (null becomes "null" string).
                    'assigned_frontdesk_id' => json_encode($assignedFrontdesk),
                    'description'           => $description,
                    'payable_amount'        => $lineTotal,
                    'paid_amount'           => 0,
                    'change_amount'         => 0,
                    'deposit_amount'        => 0,
                    'paid_at'               => null,
                    'override_at'           => null,
                    'remarks'               => "POS ({$line['name']} x{$line['quantity']})",
                    // Frozen line-item snapshot — never updated after creation.
                    'source_type'           => $sourceType,
                    'menu_id'               => (int) $line['menu_id'],
                    'item_name'             => $line['name'],
                    'unit_price'            => (int) $line['unit_price'],
                    'quantity'              => (float) $line['quantity'],
                ]);

                // StockService::out throws InsufficientStockException on race
                // with another writer; the surrounding DB transaction rolls
                // back the order header + any prior line transactions.
                $this->stock->out(
                    $sourceType,
                    (int) $line['menu_id'],
                    (float) $line['quantity'],
                    [
                        'branch_id'    => $branchId,
                        'ref_type'     => 'transaction',
                        'ref_id'       => $transaction->id,
                        'user_id'      => $userId,
                        'shift_log_id' => $shiftLogId,
                    ]
                );
            }

            return $order->fresh('lineItems');
        });
    }

    /**
     * Void a previously-checked-out POS order. Reverses the order header,
     * each line transaction, and each stock movement (via StockService::void
     * which writes a TYPE_VOID IN movement and increments inventory back).
     *
     * Idempotent: voiding an already-voided order is a no-op.
     *
     * Caller is responsible for the same-shift / same-user authorization
     * gate — this service trusts the call.
     */
    public function void(PosOrder $order, int $voidedByUserId, ?string $reason = null): PosOrder
    {
        if ($order->voided_at !== null) {
            return $order; // already voided
        }

        return DB::transaction(function () use ($order, $voidedByUserId, $reason) {
            $now = now();

            // Mark the order header.
            $order->forceFill([
                'voided_at'         => $now,
                'voided_by_user_id' => $voidedByUserId,
                'void_reason'       => $reason,
            ])->save();

            // Mark every line transaction so folio queries can filter them out.
            $lineTransactions = Transaction::where('order_id', $order->id)->get();
            foreach ($lineTransactions as $tx) {
                $tx->forceFill([
                    'voided_at'         => $now,
                    'voided_by_user_id' => $voidedByUserId,
                ])->save();

                // Reverse the stock — IN movement of the original quantity,
                // referenced back to the original transaction for audit.
                if ($tx->menu_id !== null && $tx->source_type !== null && $tx->quantity !== null) {
                    $this->stock->void(
                        $tx->source_type,
                        (int) $tx->menu_id,
                        (float) $tx->quantity,
                        [
                            'branch_id'    => $tx->branch_id,
                            'ref_type'     => 'transaction_void',
                            'ref_id'       => $tx->id,
                            'reason'       => $reason !== null && $reason !== '' ? "void: {$reason}" : 'void',
                            'user_id'      => $voidedByUserId,
                            'shift_log_id' => $order->shift_log_id,
                        ]
                    );
                }
            }

            return $order->fresh('lineItems');
        });
    }
}
