<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CashOnDrawer;
use App\Models\CheckinDetail;
use App\Models\Guest;
use App\Models\OverrideRequest;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\ShiftLog;
use App\Models\Transaction;
use App\Models\TransferedGuestReport;
use App\Models\Type;
use App\Models\User;
use App\Services\KioskBatchService;
use App\Support\ShiftResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function completeTransfer(OverrideRequest $request, ?int $approvedByUserId = null): array
    {
        $requestData = $request->request_data ?? [];

        // Get the guest and check-in detail
        $guest = Guest::find($request->guest_id);
        $check_in_detail = CheckinDetail::where('guest_id', $guest->id)->first();

        if (!$guest || !$check_in_detail) {
            return ['success' => false, 'message' => 'Guest or check-in details not found.'];
        }

        // Check if guest is still in the original room
        if ($guest->room_id != $request->from_room_id) {
            $request->delete();
            return ['success' => false, 'message' => 'Guest has already been moved from the original room.'];
        }

        // Check if target room is still available
        $toRoom = Room::find($request->to_room_id);
        if (!$toRoom || $toRoom->status !== 'Available') {
            $request->delete();
            return ['success' => false, 'message' => 'Target room is no longer available.'];
        }

        // Get the requester (frontdesk who created the request)
        $requester = User::find($request->requester_id);
        if (!$requester) {
            return ['success' => false, 'message' => 'Requester not found.'];
        }

        DB::beginTransaction();
        try {
            $assigned_frontdesk = $requester->assigned_frontdesks ?? [];

            $requesterShiftLogId = ShiftLog::where('frontdesk_id', $requester->id)
                ->whereNull('time_out')
                ->latest('time_in')
                ->value('id');
            $requesterShift = ShiftResolver::current($requester);

            // Get rate for new room. Long-stay guests have hours_stayed =
            // 24 × number_of_days, which never matches a staying_hours row
            // (only 6, 12, 18, 24 exist). Use the 24-hour rate × days for them,
            // mirroring the kiosk's long-stay flow.
            if ($guest->is_long_stay) {
                $longStayingHour = StayingHour::where('branch_id', $request->branch_id)
                    ->where('number', 24)
                    ->first();

                $new_room_rate_obj = $longStayingHour
                    ? Rate::where('branch_id', $request->branch_id)
                        ->where('type_id', $request->to_type_id)
                        ->where('is_available', true)
                        ->where('staying_hour_id', $longStayingHour->id)
                        ->first()
                    : null;

                $new_room_rate = $new_room_rate_obj
                    ? $new_room_rate_obj->amount * $guest->number_of_days
                    : 0;
            } else {
                $hours = $check_in_detail->hours_stayed;
                $new_room_rate_obj = Rate::where('branch_id', $request->branch_id)
                    ->where('type_id', $request->to_type_id)
                    ->where('is_available', true)
                    ->whereHas('stayingHour', function ($query) use ($request, $hours) {
                        $query->where('branch_id', $request->branch_id)
                            ->where('number', '=', $hours);
                    })
                    ->first();

                $new_room_rate = $new_room_rate_obj ? $new_room_rate_obj->amount : 0;
            }

            $current_room_rate = $request->current_amount;
            $excess_amount = max(0, $current_room_rate - $new_room_rate);
            $selected_status = $requestData['selected_status'] ?? 'Uncleaned';
            $save_excess = $requestData['save_excess'] ?? false;

            // Create transfer transaction
            $transaction = Transaction::create([
                'branch_id' => $request->branch_id,
                'shift_log_id' => $requesterShiftLogId,
                'checkin_detail_id' => $check_in_detail->id,
                'cash_drawer_id' => $requester->cash_drawer_id,
                'room_id' => $request->to_room_id,
                'guest_id' => $guest->id,
                'floor_id' => $toRoom->floor_id,
                'transaction_type_id' => 7,
                'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
                'description' => 'Room Transfer',
                'payable_amount' => 0,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => now()->toDateString(),
                'override_at' => null,
                'remarks' => 'Guest Transfered from Room #' . ($request->fromRoom->number ?? 'N/A') .
                    ' (' . (optional(Type::find($request->from_type_id))->name ?? 'N/A') . ') to Room #' . $toRoom->number .
                    ' (' . (optional(Type::find($request->to_type_id))->name ?? 'N/A') . ') - Reason: ' . ($request->transferReason->reason ?? 'N/A'),
                'transfer_reason_id' => $request->transfer_reason_id,
                'shift' => $requesterShift,
                'is_override' => true,
                'override_request_id' => $request->id,
                'approved_by_user_id' => $approvedByUserId,
            ]);

            // Handle excess amount deposit
            if ($save_excess && $excess_amount > 0 && $requester->cash_drawer_id) {
                Transaction::create([
                    'branch_id' => $request->branch_id,
                    'shift_log_id' => $requesterShiftLogId,
                    'checkin_detail_id' => $check_in_detail->id,
                    'cash_drawer_id' => $requester->cash_drawer_id,
                    'room_id' => $guest->room_id,
                    'guest_id' => $guest->id,
                    'floor_id' => Room::find($guest->room_id)->floor->id,
                    'transaction_type_id' => 2,
                    'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
                    'description' => 'Deposit',
                    'payable_amount' => $excess_amount,
                    'paid_amount' => $excess_amount,
                    'change_amount' => 0,
                    'deposit_amount' => $excess_amount,
                    'paid_at' => Carbon::now()->toDateTimeString(),
                    'override_at' => null,
                    'remarks' => 'Deposit From Transfer Room (Excess Amount)',
                    'shift' => $requesterShift,
                    'is_override' => false,
                ]);

                if ($requester->frontdesk) {
                    CashOnDrawer::create([
                        'branch_id' => $request->branch_id,
                        'frontdesk_id' => $requester->frontdesk->id,
                        'cash_drawer_id' => $requester->cash_drawer_id,
                        'amount' => $excess_amount,
                        'transaction_date' => now()->toDateString(),
                        'transaction_type' => 'deposit',
                        'shift' => $requesterShift,
                    ]);
                }

                CheckinDetail::where('guest_id', $guest->id)->update([
                    'total_deposit' => $guest->total_deposit + $excess_amount,
                ]);
            }

            // Update room statuses
            if ($selected_status === "Uncleaned") {
                Room::where('id', $check_in_detail->room_id)->update([
                    'time_to_clean' => now()->addHours(4),
                ]);
            }

            Room::where('id', $check_in_detail->room_id)->update([
                'status' => $selected_status,
            ]);

            Room::where('id', $request->to_room_id)->update([
                'status' => 'Occupied',
            ]);

            $branch = \App\Models\Branch::find($request->branch_id);
            $initial_deposit = $branch ? $branch->initial_deposit : 0;

            // Update guest
            Guest::where('id', $guest->id)->update([
                'previous_room_id' => $check_in_detail->room_id,
                'room_id' => $request->to_room_id,
                'type_id' => $request->to_type_id,
                'rate_id' => $new_room_rate_obj ? $new_room_rate_obj->id : $guest->rate_id,
                'static_amount' => ($new_room_rate + $initial_deposit),
            ]);

            // Update check-in transaction
            $checkInTransaction = Transaction::where('checkin_detail_id', $check_in_detail->id)
                ->where('description', 'Guest Check In')
                ->first();

            if ($checkInTransaction) {
                $checkInTransaction->update([
                    'remarks' => 'Guest Checked In at room #' . $toRoom->number,
                    'payable_amount' => $new_room_rate,
                    'paid_amount' => $new_room_rate,
                    'change_amount' => 0,
                    'paid_at' => Carbon::now()->toDateTimeString(),
                    'room_id' => $request->to_room_id,
                    'floor_id' => $toRoom->floor_id,
                ]);
            }

            // Move Deposit transactions to the new room so the audit trail
            // and any room-scoped report show the deposit under the guest's
            // current room (matches Bug ⑧ fix in TransferRoom.php).
            Transaction::where('checkin_detail_id', $check_in_detail->id)
                ->where('transaction_type_id', 2)
                ->update([
                    'room_id' => $request->to_room_id,
                    'floor_id' => $toRoom->floor_id,
                ]);

            // Create transfer report
            TransferedGuestReport::create([
                'checkin_detail_id' => $check_in_detail->id,
                'previous_room_id' => $check_in_detail->room_id,
                'new_room_id' => $toRoom->id,
                'rate_id' => $guest->rate_id,
                'previous_amount' => $check_in_detail->static_room_amount,
                'new_amount' => $new_room_rate,
                'original_check_in_time' => $checkInTransaction ? $checkInTransaction->created_at : now(),
            ]);

            // Update check-in detail
            CheckinDetail::where('guest_id', $guest->id)->update([
                'type_id' => $request->to_type_id,
                'room_id' => $request->to_room_id,
                'rate_id' => $new_room_rate_obj ? $new_room_rate_obj->id : $guest->rate_id,
                'static_room_amount' => $new_room_rate,
                'static_amount' => ($new_room_rate + $initial_deposit),
            ]);

            // Log activity
            ActivityLog::create([
                'branch_id' => $request->branch_id,
                'user_id' => $approvedByUserId ?? $request->requester_id,
                'activity' => 'Room Transfer (Override Approved)',
                'description' => 'Guest ' . $guest->name . ' transferred from Room #' . $request->fromRoom->number . ' to Room #' . $toRoom->number,
            ]);

            // Mark override request as completed
            // If supervisor_id is set, it was manually approved; otherwise it was auto-approved
            $status = $request->supervisor_id ? 'approved' : 'auto_approved';

            // Store guest/room info in request_data for historical reference (before guest might be deleted)
            $historicalData = array_merge($requestData, [
                'guest_name' => $guest->name,
                'from_room_number' => $request->fromRoom->number ?? null,
                'to_room_number' => $toRoom->number ?? null,
                'requester_name' => $requester->name ?? null,
            ]);

            $request->update([
                'status' => $status,
                'request_data' => $historicalData,
            ]);

            DB::commit();

            // Clear the kiosk picked slot for the source room (same fix as
            // TransferRoom.php). Without this the slot stays orphaned after
            // the kiosk-checked-in guest is moved away.
            KioskBatchService::refreshSlot(
                $request->branch_id,
                $check_in_detail->type_id,
            );

            // Heal the destination type's batch — destination room just became
            // Occupied so any active slot pointing to it is stale. Mirrors the
            // safety call in TransferRoom.php.
            KioskBatchService::refreshIfStale(
                $request->branch_id,
                $request->to_type_id,
            );

            return ['success' => true, 'message' => 'Transfer completed successfully.'];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to complete transfer: ' . $e->getMessage()];
        }
    }
}
