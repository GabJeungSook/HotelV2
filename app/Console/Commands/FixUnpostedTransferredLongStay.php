<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\Guest;
use App\Models\Rate;
use App\Models\StayingHour;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recovers financial fields for long-stay kiosk guests whose room charge was
 * silently zeroed out by the TransferRoom rate-lookup bug.
 *
 * Bug summary: TransferRoom looked up the destination Rate by matching
 * `staying_hours.number = checkin_details.hours_stayed`. For long-stay
 * guests `hours_stayed = 24 × number_of_days` (e.g. 48, 72) which never
 * matches a real `staying_hours` row, so the lookup returned NULL and
 * downstream writes used `new_room_rate = 0`. That overwrote:
 *   • guests.static_amount        with 0 + initial_deposit (e.g. 200)
 *   • checkin_details.static_room_amount with 0
 *   • checkin_details.static_amount with 0 + initial_deposit
 *   • transactions.payable_amount  (Check In row) with 0
 *   • transactions.paid_amount     (Check In row) with 0
 *
 * This recovery recomputes the correct values from the destination type's
 * 24-hour rate × number_of_days and writes them back. Use --dry-run first.
 */
class FixUnpostedTransferredLongStay extends Command
{
    protected $signature = 'transfers:fix-unposted-longstay
        {--dry-run : Show the rows that would be fixed without writing}
        {--branch= : Restrict to a specific branch_id}
        {--from= : Only fix guests created on or after this date (YYYY-MM-DD)}
        {--to= : Only fix guests created on or before this date (YYYY-MM-DD)}
        {--guest= : Fix a single guests.id only}';

    protected $description = 'Recover static_amount/payable_amount for long-stay kiosk guests whose values were zeroed by the Transfer rate-lookup bug';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $branchId = $this->option('branch');
        $from = $this->option('from');
        $to = $this->option('to');
        $guestId = $this->option('guest');

        $candidates = $this->findCandidates($branchId, $from, $to, $guestId);

        if ($candidates->isEmpty()) {
            $this->info('No affected guests found for the given filters.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$candidates->count()} affected guest(s).");
        $this->newLine();

        $rows = [];
        $fixedCount = 0;
        $skippedCount = 0;

        foreach ($candidates as $guest) {
            $result = $this->buildPlanForGuest($guest);

            if (! $result['ok']) {
                $rows[] = [
                    $guest->id,
                    $guest->name,
                    $guest->room_id,
                    'SKIP',
                    $result['reason'],
                ];
                $skippedCount++;
                continue;
            }

            $rows[] = [
                $guest->id,
                $guest->name,
                $guest->room->number ?? $guest->room_id,
                "₱{$result['old']['guest_static_amount']} → ₱{$result['new']['guest_static_amount']}",
                "checkin: ₱{$result['old']['cd_static_room_amount']} → ₱{$result['new']['cd_static_room_amount']}; tx: ₱{$result['old']['tx_payable']} → ₱{$result['new']['tx_payable']}",
            ];

            if (! $dryRun) {
                $this->applyFix($result);
            }

            $fixedCount++;
        }

        $this->table(
            ['Guest ID', 'Name', 'Room', 'static_amount', 'Detail'],
            $rows
        );

        $this->newLine();
        $this->info(sprintf(
            '%s%d fixed, %d skipped.',
            $dryRun ? '[DRY RUN] Would have ' : '',
            $fixedCount,
            $skippedCount
        ));

        return self::SUCCESS;
    }

    /**
     * Find guests likely affected by the bug:
     *   • is_long_stay = 1
     *   • previous_room_id IS NOT NULL (i.e. were transferred)
     *   • static_amount equals branch.initial_deposit (the symptom of the bug)
     */
    private function findCandidates($branchId, $from, $to, $guestId)
    {
        $query = Guest::query()
            ->where('is_long_stay', 1)
            ->whereNotNull('previous_room_id')
            ->whereHas('checkInDetail')
            ->with(['checkInDetail', 'room.type']);

        if ($guestId) {
            return $query->where('id', $guestId)->get();
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Match the symptom: static_amount equals the branch's initial_deposit
        // (meaning: 0 room charge + initial_deposit was written).
        $depositByBranch = Branch::pluck('initial_deposit', 'id')
            ->map(fn ($value) => (float) $value);

        return $query->get()->filter(function ($guest) use ($depositByBranch) {
            $initialDeposit = $depositByBranch[$guest->branch_id] ?? 0.0;
            return (float) $guest->static_amount === $initialDeposit;
        });
    }

    /**
     * Compute the correct values for a single guest.
     */
    private function buildPlanForGuest(Guest $guest): array
    {
        $checkin = $guest->checkInDetail;

        if (! $checkin) {
            return ['ok' => false, 'reason' => 'no checkin_detail'];
        }

        if ($checkin->is_check_out) {
            return ['ok' => false, 'reason' => 'already checked out — manual review'];
        }

        $longStayingHour = StayingHour::where('branch_id', $guest->branch_id)
            ->where('number', 24)
            ->first();

        if (! $longStayingHour) {
            return ['ok' => false, 'reason' => 'branch has no 24-hour staying_hour'];
        }

        $rate = Rate::where('branch_id', $guest->branch_id)
            ->where('type_id', $guest->type_id)
            ->where('is_available', true)
            ->where('staying_hour_id', $longStayingHour->id)
            ->first();

        if (! $rate) {
            return ['ok' => false, 'reason' => "no 24h rate for type_id={$guest->type_id}"];
        }

        $days = max(1, (int) $guest->number_of_days);
        $roomCharge = (float) $rate->amount * $days;
        $initialDeposit = (float) (Branch::where('id', $guest->branch_id)->value('initial_deposit') ?? 0);
        $newGuestStaticAmount = $roomCharge + $initialDeposit;

        $checkInTx = Transaction::where('checkin_detail_id', $checkin->id)
            ->where('description', 'Guest Check In')
            ->first();

        return [
            'ok' => true,
            'guest' => $guest,
            'checkin' => $checkin,
            'check_in_transaction' => $checkInTx,
            'new' => [
                'guest_static_amount' => $newGuestStaticAmount,
                'cd_static_room_amount' => $roomCharge,
                'cd_static_amount' => $newGuestStaticAmount,
                'tx_payable' => $roomCharge,
            ],
            'old' => [
                'guest_static_amount' => (float) $guest->static_amount,
                'cd_static_room_amount' => (float) $checkin->static_room_amount,
                'cd_static_amount' => (float) $checkin->static_amount,
                'tx_payable' => $checkInTx ? (float) $checkInTx->payable_amount : null,
            ],
        ];
    }

    /**
     * Apply the recovery in a single transaction so a guest is never left
     * half-fixed across the three rows.
     */
    private function applyFix(array $plan): void
    {
        DB::transaction(function () use ($plan) {
            $plan['guest']->update([
                'static_amount' => $plan['new']['guest_static_amount'],
            ]);

            $plan['checkin']->update([
                'static_room_amount' => $plan['new']['cd_static_room_amount'],
                'static_amount' => $plan['new']['cd_static_amount'],
            ]);

            if ($plan['check_in_transaction']) {
                $plan['check_in_transaction']->update([
                    'payable_amount' => $plan['new']['tx_payable'],
                    'paid_amount' => $plan['new']['tx_payable'],
                ]);
            }
        });
    }
}
