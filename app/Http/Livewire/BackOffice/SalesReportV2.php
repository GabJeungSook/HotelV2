<?php

namespace App\Http\Livewire\BackOffice;

use Livewire\Component;
use App\Models\Frontdesk;
use App\Models\CheckinDetail;
use App\Models\Transaction;
use App\Models\Floor;
use App\Models\Expense;
use App\Models\Remittance;
use App\Models\ShiftLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Sales Report V2 - Occupancy-Based
 *
 * This report shows transactions for guests who were OCCUPYING rooms during
 * the selected date range, including guests who checked in before the range
 * but were still staying (forward/carry-over guests).
 *
 * Key differences from original SalesReport:
 * 1. Date filter = guest occupancy period (not transaction created_at)
 * 2. Frontdesk filter = who processed the transaction (not who checked in)
 * 3. Uses LEFT JOIN on shift_logs (handles NULL shift_log_id)
 */
class SalesReportV2 extends Component
{
    // Optional week override (set by Archives page)
    public $weekStart = null;

    // Filter mode: 'date_range' or 'shift'
    public string $filterMode = 'shift';

    // Date range mode properties
    public $date_from;
    public $date_to;
    public $frontdesk;
    public $frontdesk_name;

    // Shift mode properties
    public $selectedShiftLogId;
    public array $availableShiftSessions = [];

    public array $salesRows = [];
    public float $totalSales = 0;
    public array $summaryByType = [];
    public array $roomSummary = [];

    public $expensesRows;
    public float $expensesTotal = 0;
    public $remittanceRows;
    public float $remittanceTotal = 0;
    public float $netSales = 0;
    public int $forwardedCount = 0;
    public int $unclaimedCount = 0;

    // Forwarded totals
    public float $forwardedRoom = 0;
    public float $forwardedRoomDeposit = 0;
    public float $forwardedGuestDeposit = 0;

    public float $unclaimedDepositTotal = 0;

    // Checkout and cashout totals
    public float $totalCashouts = 0;
    public float $checkoutRoomAmount = 0;
    public float $checkoutRoomDeposit = 0;
    public float $remainingRoomDeposit = 0;

    // Card modal
    public bool $showCardModal = false;
    public string $cardModalTitle = '';
    public array $cardModalRows = [];
    public float $cardModalTotal = 0;

    // Shift counts (for shift mode display)
    public int $shiftCheckins = 0;
    public int $shiftCheckouts = 0;

    public function mount()
    {
        $this->date_from = now()->toDateString();
        $this->date_to = now()->toDateString();
        $this->salesRows = [];
        $this->totalSales = 0;
        $this->summaryByType = [];
        $this->expensesRows = collect();
        $this->expensesTotal = 0;
        $this->roomSummary = [];
        $this->forwardedRoom = 0;
        $this->forwardedRoomDeposit = 0;
        $this->forwardedGuestDeposit = 0;

        $this->loadAvailableShiftSessions();
        if (!empty($this->availableShiftSessions)) {
            $this->selectedShiftLogId = end($this->availableShiftSessions)['id'];
        }
        $this->generateReport();
    }

    public function render()
    {
        return view('livewire.back-office.sales-report-v2', [
            'frontdesks' => Frontdesk::where('branch_id', auth()->user()->branch_id)->get(),
        ]);
    }

    /**
     * Load all completed shift sessions (grouped by shift TYPE + DATE).
     */
    private function loadAvailableShiftSessions(): void
    {
        $weekStart = $this->weekStart ? Carbon::parse($this->weekStart)->startOfWeek() : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $shiftLogs = ShiftLog::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->whereNotNull('time_out') // Completed shifts only
            ->whereBetween('time_in', [$weekStart, $weekEnd])
            ->with('frontdesk:id,name')
            ->orderBy('time_in','asc')
            ->get();

        // Group by SHIFT TYPE + DATE (not time proximity)
        $sessions = [];
        foreach ($shiftLogs as $log) {
            $shiftType = $this->getShiftType($log->time_in);
            $shiftDate = $log->time_in->format('Y-m-d');
            $key = $shiftType . '_' . $shiftDate;

            if (!isset($sessions[$key])) {
                $sessions[$key] = [
                    'id' => $log->id,
                    'logs' => [],
                    'log_ids' => [],
                    'time_in' => $log->time_in,
                    'time_out' => $log->time_out,
                    'shift_type' => $shiftType,
                    'shift_date' => $shiftDate,
                    'frontdesks' => [],
                ];
            }

            $sessions[$key]['logs'][] = $log;
            $sessions[$key]['log_ids'][] = $log->id;
            $sessions[$key]['frontdesks'][] = $log->frontdesk?->name ?? 'Unknown';

            // Use earliest time_in and latest time_out
            if ($log->time_in < $sessions[$key]['time_in']) {
                $sessions[$key]['time_in'] = $log->time_in;
            }
            if ($log->time_out > $sessions[$key]['time_out']) {
                $sessions[$key]['time_out'] = $log->time_out;
            }
        }

        // Sort by time_in descending and format labels
        $this->availableShiftSessions = collect($sessions)
            ->sortBy('time_in')
            ->map(function ($s) {
                $frontdeskNames = implode(', ', array_unique($s['frontdesks']));

                return [
                    'id' => $s['log_ids'][0], // Primary ID
                    'log_ids' => $s['log_ids'],
                    'label' => $s['shift_type'] . ' ' . $s['time_in']->format('M j')
                             . ' - ' . $frontdeskNames
                             . ' (' . $s['time_in']->format('g:i A') . ' - ' . $s['time_out']->format('g:i A') . ')',
                    'frontdesks' => $frontdeskNames,
                    'time_in' => $s['time_in']->toIso8601String(),
                    'time_out' => $s['time_out']->toIso8601String(),
                    'time_in_formatted' => $s['time_in']->format('F d, Y g:i A'),
                    'time_out_formatted' => $s['time_out']->format('F d, Y g:i A'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get check-in and check-out counts for a shift period.
     */
    private function getShiftCounts(Carbon $timeIn, Carbon $timeOut): array
    {
        $branchId = auth()->user()->branch_id;

        // Check-ins: checkin_details with check_in_at during this shift
        $checkins = CheckinDetail::whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('check_in_at', [$timeIn, $timeOut])
            ->count();

        // Check-outs: checkin_details with check_out_at during this shift
        $checkouts = CheckinDetail::whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('check_out_at', [$timeIn, $timeOut])
            ->count();

        // Handle overlapping shifts: add guests checked out during the overlap period
        // to the check-in count (they were processed by the previous shift but belong to this shift's count)
        if ($this->filterMode === 'shift' && $this->selectedShiftLogId) {
            $shiftLog = ShiftLog::find($this->selectedShiftLogId);
            if ($shiftLog) {
                $prevShift = ShiftLog::whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
                    ->where('time_in', '<', $shiftLog->time_in)
                    ->orderBy('time_in', 'desc')
                    ->first();

                if ($prevShift && $prevShift->time_out > $shiftLog->time_in) {
                    // Overlap exists: count guests who checked in before this shift
                    // but checked out during the overlap (previous shift processed their checkout)
                    $overlapCheckins = CheckinDetail::whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                        ->where('check_in_at', '<', $shiftLog->time_in)
                        ->whereBetween('check_out_at', [$shiftLog->time_in, $prevShift->time_out])
                        ->count();
                    $checkins += $overlapCheckins;
                }
            }
        }

        return ['checkins' => $checkins, 'checkouts' => $checkouts];
    }

    /**
     * Determine shift type from time_in hour.
     * AM: 6:00 AM - 7:59 PM (hours 6-19)
     * PM: 8:00 PM - 5:59 AM (hours 20-23, 0-5)
     */
    private function getShiftType(Carbon $timeIn): string
    {
        $hour = $timeIn->hour;
        return ($hour >= 6 && $hour < 20) ? 'AM' : 'PM';
    }

    /**
     * When filter mode changes, load shift sessions if needed.
     */
    public function updatedFilterMode()
    {
        if ($this->filterMode === 'shift' && empty($this->availableShiftSessions)) {
            $this->loadAvailableShiftSessions();
        }
    }

    public function generateReport()
    {
        $frontdesk = Frontdesk::find($this->frontdesk);
        $this->frontdesk_name = $frontdesk?->name;

        $this->salesRows = $this->buildSalesRows();
        $this->totalSales = collect($this->salesRows)->sum('total');
        $this->summaryByType = $this->buildSummaryByType();
        $this->buildExpensesSummary();
        $this->buildRemittanceSummary();
        $this->buildRoomSummary();
        // Count forwarded stays FIRST (needed by calculateForwardedTotals for count × 200)
        $this->forwardedCount = collect($this->salesRows)
            ->filter(fn($row) => ($row['is_forwarded_guest_row'] ?? false)
                && $row['transaction_type'] === 'FWD ROOM'
                && !str_contains($row['remarks'] ?? '', 'Unclaimed'))
            ->count();

        $this->calculateShiftCounts();
        $this->calculateForwardedTotals();

        // Calculate net sales (Gross - Expenses)
        $this->netSales = $this->totalSales - $this->expensesTotal;

        // Count and sum unclaimed deposit guests (checked out)
        $unclaimedRows = collect($this->salesRows)
            ->filter(fn($row) => ($row['is_forwarded_guest_row'] ?? false) && str_contains($row['remarks'] ?? '', 'Unclaimed'));
        $this->unclaimedCount = $unclaimedRows->count();
        $this->unclaimedDepositTotal = (float) $unclaimedRows->sum('amount');

        // Calculate cashout and checkout totals
        $this->totalCashouts = (float) ($this->summaryByType['cashouts'] ?? 0);
        $this->calculateCheckoutTotals();
    }

    /**
     * Open card detail modal with filtered rows for the clicked card type.
     */
    public function openCardModal(string $type): void
    {
        $titles = [
            'room_charges' => 'Room Charges',
            'extensions' => 'Extensions',
            'amenities' => 'Amenities',
            'food' => 'Food and Beverages',
            'damages' => 'Damage Charges',
            'transfers' => 'Transfers',
            'room_deposits' => 'Room Deposits',
            'guest_deposits' => 'Guest Deposits',
            'cashouts' => 'Cashouts',
            'checkout_room' => 'Checkout Room Amount',
            'checkout_room_deposit' => 'Checkout Room Deposit',
            'remaining_room_deposit' => 'Remaining Room Deposit',
            'fwd_room' => 'Forwarded Room',
            'fwd_room_deposit' => 'Forwarded Room Deposit',
            'fwd_guest_deposit' => 'Forwarded Guest Deposit',
            'unclaimed_deposits' => 'Unclaimed Guest Deposits',
        ];

        $this->cardModalTitle = $titles[$type] ?? $type;
        $rows = collect($this->salesRows);

        $typeFilterMap = [
            'room_charges' => fn($r) => $r['transaction_type_id'] == 1,
            'extensions' => fn($r) => $r['transaction_type_id'] == 6,
            'amenities' => fn($r) => $r['transaction_type_id'] == 8,
            'food' => fn($r) => $r['transaction_type_id'] == 9,
            'damages' => fn($r) => $r['transaction_type_id'] == 4,
            'transfers' => fn($r) => $r['transaction_type_id'] == 7,
            'cashouts' => fn($r) => $r['transaction_type_id'] == 5,
            'room_deposits' => fn($r) => ($r['transaction_type_id'] == 2 && (str_contains(strtolower($r['remarks'] ?? ''), 'room key') || str_contains(strtolower($r['remarks'] ?? ''), 'tv remote'))) || (($r['is_forwarded_guest_row'] ?? false) && $r['transaction_type'] === 'FWD ROOM DEPOSIT'),
            'guest_deposits' => fn($r) => $r['transaction_type_id'] == 2 && !str_contains(strtolower($r['remarks'] ?? ''), 'room key') && !str_contains(strtolower($r['remarks'] ?? ''), 'tv remote'),
            'fwd_room' => fn($r) => ($r['is_forwarded_guest_row'] ?? false) && $r['transaction_type'] === 'FWD ROOM',
            'fwd_room_deposit' => fn($r) => ($r['is_forwarded_guest_row'] ?? false) && $r['transaction_type'] === 'FWD ROOM DEPOSIT',
            'fwd_guest_deposit' => fn($r) => ($r['is_forwarded_guest_row'] ?? false) && $r['transaction_type'] === 'FWD GUEST DEPOSIT',
            'unclaimed_deposits' => fn($r) => ($r['is_forwarded_guest_row'] ?? false) && str_contains($r['remarks'] ?? '', 'Unclaimed'),
        ];

        if (isset($typeFilterMap[$type])) {
            $filtered = $rows->filter($typeFilterMap[$type])->values();
            $this->cardModalRows = $filtered->toArray();
            $this->cardModalTotal = (float) $filtered->sum('amount');

            // Override totals for count-based deposits (room deposits are always 200 per guest)
            if ($type === 'room_deposits') {
                $this->cardModalTotal = ($this->shiftCheckins + $this->forwardedCount) * 200;
            } elseif ($type === 'checkout_room_deposit') {
                // Handled by buildCheckoutModalRows but won't reach here
            }
        } elseif (in_array($type, ['checkout_room', 'checkout_room_deposit', 'remaining_room_deposit'])) {
            $this->buildCheckoutModalRows($type);
        } else {
            $this->cardModalRows = [];
            $this->cardModalTotal = 0;
        }

        $this->showCardModal = true;
    }

    /**
     * Build modal rows for checkout-related cards (requires DB query for checkout checkin IDs).
     */
    private function buildCheckoutModalRows(string $type): void
    {
        $range = $this->getFilterRange();
        $branchId = auth()->user()->branch_id;

        $checkoutDetails = CheckinDetail::query()
            ->with(['guest', 'room.type'])
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('check_out_at', [$range['start'], $range['end']])
            ->get();

        // Batch-load transactions for all checkout guests (fixes N+1)
        $checkoutIds = $checkoutDetails->pluck('id')->toArray();
        $allCheckoutTransactions = Transaction::whereIn('checkin_detail_id', $checkoutIds)
            ->whereIn('transaction_type_id', [1, 2])
            ->get()
            ->groupBy('checkin_detail_id');

        $rows = [];
        foreach ($checkoutDetails as $cd) {
            $cdTxns = $allCheckoutTransactions->get($cd->id, collect());
            if ($type === 'checkout_room') {
                $amount = (float) $cdTxns->where('transaction_type_id', 1)->sum('payable_amount');
            } elseif ($type === 'checkout_room_deposit') {
                $amount = (float) $cdTxns->where('transaction_type_id', 2)
                    ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
                    ->sum('payable_amount');
            } else {
                continue;
            }

            if ($amount > 0) {
                $checkInAt = $cd->check_in_at ? Carbon::parse($cd->check_in_at) : null;
                $checkOutAt = $cd->check_out_at ? Carbon::parse($cd->check_out_at) : null;

                $rows[] = [
                    'room_number' => $cd->room?->number ?? '—',
                    'room_type' => $cd->room?->type?->name ?? '—',
                    'guest_name' => strtoupper($cd->guest?->name ?? '—'),
                    'transaction_type' => $type === 'checkout_room' ? 'Room Charge' : 'Room Deposit',
                    'check_in' => $checkInAt?->format('m-d-Y h:iA') ?? '—',
                    'check_out' => $checkOutAt?->format('m-d-Y h:iA') ?? '—',
                    'amount' => $amount,
                    'remarks' => 'Checked out',
                    'transaction_date' => $checkOutAt?->format('m-d-Y h:iA') ?? '—',
                ];
            }
        }

        if ($type === 'remaining_room_deposit') {
            // Remaining = non-checkout guests who still have room deposits
            $checkoutIds = $checkoutDetails->pluck('id')->toArray();
            $remainingRows = collect($this->salesRows)
                ->filter(function ($r) use ($checkoutIds) {
                    if ($r['transaction_type_id'] == 2 && (str_contains(strtolower($r['remarks'] ?? ''), 'room key') || str_contains(strtolower($r['remarks'] ?? ''), 'tv remote'))) {
                        return true; // Current shift room deposits not from checkouts
                    }
                    if (($r['is_forwarded_guest_row'] ?? false) && $r['transaction_type'] === 'FWD ROOM DEPOSIT') {
                        return true;
                    }
                    return false;
                })
                ->values()
                ->toArray();

            // Filter out checkout guests by checkin_detail_id and deduplicate
            $checkoutCheckinDetailIds = $checkoutDetails->pluck('id')->toArray();
            $remainingRows = collect($remainingRows)->filter(function ($r) use ($checkoutCheckinDetailIds) {
                return !in_array($r['checkin_detail_id'] ?? null, $checkoutCheckinDetailIds);
            })->unique('checkin_detail_id')->values()->toArray();

            $this->cardModalRows = $remainingRows;
            $this->cardModalTotal = count($remainingRows) * 200;
            return;
        }

        $this->cardModalRows = $rows;
        $this->cardModalTotal = (float) collect($rows)->sum('amount');
    }

    /**
     * Calculate check-in and check-out counts for the current filter range.
     */
    private function calculateShiftCounts(): void
    {
        $range = $this->getFilterRange();
        $counts = $this->getShiftCounts($range['start'], $range['end']);
        $this->shiftCheckins = $counts['checkins'];
        $this->shiftCheckouts = $counts['checkouts'];
    }

    /**
     * Calculate checkout-specific totals (room charges and room deposits from checkouts in current shift).
     */
    private function calculateCheckoutTotals(): void
    {
        $range = $this->getFilterRange();
        $branchId = auth()->user()->branch_id;

        // Find guests who checked out during current shift/range
        $checkoutIds = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('check_out_at', [$range['start'], $range['end']])
            ->pluck('id')
            ->toArray();

        if (empty($checkoutIds)) {
            $this->checkoutRoomAmount = 0;
            $this->checkoutRoomDeposit = 0;
            $this->remainingRoomDeposit = ($this->shiftCheckins + $this->forwardedCount) * 200;
            return;
        }

        // Sum room charges (type 1) for checked-out guests
        $this->checkoutRoomAmount = (float) Transaction::whereIn('checkin_detail_id', $checkoutIds)
            ->where('transaction_type_id', 1)
            ->sum('payable_amount');

        // Checkout room deposit = checkout count × 200 (each guest returns their 200 deposit)
        $this->checkoutRoomDeposit = $this->shiftCheckouts * 200;

        // Remaining = guests still occupying at shift end × 200
        // Still occupying = check-ins + forwarded - checkouts
        $remainingGuests = max(0, $this->shiftCheckins + $this->forwardedCount - $this->shiftCheckouts);
        $this->remainingRoomDeposit = $remainingGuests * 200;
    }

    /**
     * Calculate forwarded room and deposit totals.
     *
     * For shift mode: Shows the ORIGINAL room charge + deposit collected by
     * previous shift for guests who are still occupying (forwarded guests).
     */
    private function calculateForwardedTotals(): void
    {
        // Only calculate for shift mode
        if ($this->filterMode !== 'shift' || !$this->selectedShiftLogId) {
            // For date range mode, use transactions in current range from forwarded guests
            $forwardedRows = collect($this->salesRows)->filter(fn($row) => $row['is_forwarded']);
            $this->forwardedRoom = $forwardedRows->where('transaction_type_id', 1)->sum('amount');
            // Split deposits by type
            $forwardedDeposits = $forwardedRows->where('transaction_type_id', 2);
            $this->forwardedRoomDeposit = $forwardedDeposits->filter(function ($row) {
                $remarks = strtolower($row['remarks'] ?? '');
                return str_contains($remarks, 'room key') || str_contains($remarks, 'tv remote');
            })->sum('amount');
            $originalGuestDeposit = $forwardedDeposits->filter(function ($row) {
                $remarks = strtolower($row['remarks'] ?? '');
                return !str_contains($remarks, 'room key') && !str_contains($remarks, 'tv remote');
            })->sum('amount');

            // Subtract cashouts (type 5) from forwarded guest deposits
            $forwardedCashouts = $forwardedRows->where('transaction_type_id', 5)->sum('amount');
            $this->forwardedGuestDeposit = max(0, $originalGuestDeposit - $forwardedCashouts);
            return;
        }

        // Shift mode: FWD Room from FWD rows
        $fwdRows = collect($this->salesRows)->filter(fn($row) => !empty($row['is_forwarded_guest_row']));
        $this->forwardedRoom = (float) $fwdRows->where('transaction_type', 'FWD ROOM')->sum('amount');

        // FWD Room Deposit = forwarded guest count × 200 (each guest has exactly one 200 deposit)
        $this->forwardedRoomDeposit = $this->forwardedCount * 200;

        // FWD Guest Deposit from cumulative chain (running balance across all previous shifts)
        $this->calculateForwardedDepositsFromPreviousShift();
    }

    /**
     * Calculate forwarded guest deposit using per-guest amounts from salesRows.
     * Each guest's net deposit is MAX(0, deposits - cashouts), ensuring no guest
     * contributes a negative balance. This matches the modal list calculation.
     */
    private function calculateForwardedDepositsFromPreviousShift(): void
    {
        // Reuse the per-guest FWD GUEST DEPOSIT rows already computed by getForwardedGuestRows()
        $fwdGuestDepositRows = collect($this->salesRows)
            ->filter(fn($row) => ($row['is_forwarded_guest_row'] ?? false)
                && $row['transaction_type'] === 'FWD GUEST DEPOSIT');

        $this->forwardedGuestDeposit = (float) $fwdGuestDepositRows->sum('amount');
    }

    public function resetFilters()
    {
        $this->reset(['frontdesk', 'date_from', 'date_to', 'selectedShiftLogId']);
        $this->filterMode = 'date_range';
        $this->date_from = now()->toDateString();
        $this->date_to = now()->toDateString();
        $this->generateReport();
    }

    /**
     * Get the date/time range based on filter mode.
     */
    private function getFilterRange(): array
    {
        if ($this->filterMode === 'shift' && $this->selectedShiftLogId) {
            // Use the session's grouped time range (min time_in / max time_out across all logs)
            // instead of a single shift log's times, to handle sessions with multiple logs
            $session = collect($this->availableShiftSessions)
                ->firstWhere('id', $this->selectedShiftLogId);

            if ($session) {
                return [
                    'start' => Carbon::parse($session['time_in']),
                    'end' => Carbon::parse($session['time_out']),
                ];
            }
        }

        // Default: date range mode
        $startDate = $this->date_from ?? now()->toDateString();
        $endDate = $this->date_to ?? now()->toDateString();

        return [
            'start' => Carbon::parse($startDate)->startOfDay(),
            'end' => Carbon::parse($endDate)->endOfDay(),
        ];
    }

    /**
     * Find check-in details where guest was OCCUPYING room during the filter period.
     *
     * A guest is considered "occupying" if:
     * - They checked in ON or BEFORE the end time, AND
     * - They haven't checked out yet OR checked out ON or AFTER the start time
     */
    private function getOccupyingCheckinIds(): array
    {
        $range = $this->getFilterRange();

        return CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->where('check_in_at', '<=', $range['end'])
            ->where(function ($q) use ($range) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>=', $range['start']);
            })
            ->pluck('id')
            ->toArray();
    }

    /**
     * Build sales rows for all transactions of occupying guests.
     * Uses LEFT JOIN on shift_logs to handle transactions with NULL shift_log_id.
     * Filters by transaction processor (shift_log.frontdesk_id), not check-in frontdesk.
     *
     * For shift mode: Also includes "forwarded guest" rows - guests who checked in
     * during a previous shift but are still occupying when this shift started.
     */
    private function buildSalesRows(): array
    {
        $occupyingIds = $this->getOccupyingCheckinIds();
        $range = $this->getFilterRange();

        // For shift mode: find overlap guests (checked in before this shift,
        // checked out during the overlap period with previous shift)
        $overlapCheckinIds = [];
        if ($this->filterMode === 'shift' && $this->selectedShiftLogId) {
            $shiftLog = ShiftLog::find($this->selectedShiftLogId);
            if ($shiftLog) {
                $branchId = auth()->user()->branch_id;
                $prevShift = ShiftLog::whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
                    ->where('time_in', '<', $shiftLog->time_in)
                    ->orderBy('time_in', 'desc')
                    ->first();
                if ($prevShift && $prevShift->time_out > $shiftLog->time_in) {
                    $overlapCheckinIds = CheckinDetail::whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                        ->where('check_in_at', '<', $shiftLog->time_in)
                        ->whereBetween('check_out_at', [$shiftLog->time_in, $prevShift->time_out])
                        ->pluck('id')
                        ->toArray();
                }
            }
        }

        // Get transaction rows
        $transactionRows = $this->getTransactionRows($occupyingIds, $range, $overlapCheckinIds);

        // For shift mode: Also get forwarded guest rows (occupying but no transactions this shift)
        $forwardedGuestRows = [];
        if ($this->filterMode === 'shift' && $this->selectedShiftLogId) {
            $forwardedGuestRows = $this->getForwardedGuestRows($range);
        }

        // Merge and sort by room number
        return collect($transactionRows)
            ->merge($forwardedGuestRows)
            ->sortBy('room_number')
            ->values()
            ->toArray();
    }

    /**
     * Get transaction rows for occupying guests.
     */
    private function getTransactionRows(array $occupyingIds, array $range, array $overlapCheckinIds = []): array
    {
        if (empty($occupyingIds)) {
            return [];
        }

        $query = DB::table('transactions as tr')
            ->leftJoin('shift_logs as sl', 'sl.id', '=', 'tr.shift_log_id')
            ->leftJoin('users as u', 'u.id', '=', 'sl.frontdesk_id')
            ->leftJoin('checkin_details as cd', 'cd.id', '=', 'tr.checkin_detail_id')
            ->leftJoin('guests as g', 'g.id', '=', 'tr.guest_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'tr.room_id')
            ->leftJoin('types as t', 't.id', '=', 'r.type_id')
            ->leftJoin('transaction_types as tt', 'tt.id', '=', 'tr.transaction_type_id')
            ->whereIn('tr.checkin_detail_id', $occupyingIds)
            // Filter transactions by the selected range,
            // OR include check-in transactions (type 1) for guests whose check_in_at is in range
            // (ensures room charges match the check-in badge count)
            ->where(function ($q) use ($range, $overlapCheckinIds) {
                $q->whereBetween('tr.created_at', [$range['start'], $range['end']])
                  ->orWhere(function ($q2) use ($range) {
                      $q2->where('tr.transaction_type_id', 1)
                         ->whereBetween('cd.check_in_at', [$range['start'], $range['end']]);
                  });
                // Include check-in transactions for overlap guests
                // (checked in before this shift, checked out during overlap with previous shift)
                if (!empty($overlapCheckinIds)) {
                    $q->orWhere(function ($q3) use ($overlapCheckinIds) {
                        $q3->where('tr.transaction_type_id', 1)
                           ->whereIn('tr.checkin_detail_id', $overlapCheckinIds);
                    });
                }
            })
            // Filter by WHO PROCESSED the transaction (only in date_range mode)
            ->when($this->filterMode === 'date_range' && $this->frontdesk, function ($q) {
                $q->where('sl.frontdesk_id', $this->frontdesk);
            })
            ->select([
                'tr.checkin_detail_id',
                'r.number as room_number',
                'r.id as room_id',
                't.name as room_type',
                'g.name as guest_name',
                'tt.name as transaction_type',
                'tt.id as transaction_type_id',
                'cd.check_in_at',
                'cd.check_out_at',
                'cd.hours_stayed',
                'tr.payable_amount',
                'tr.paid_amount',
                'tr.is_override',
                'tr.remarks',
                'tr.created_at as transaction_date',
                'u.name as processed_by',
                'sl.shift as shift',
                // Subquery to get the check-in transaction's created_at timestamp
                DB::raw('(SELECT t2.created_at FROM transactions t2
                          WHERE t2.checkin_detail_id = tr.checkin_detail_id
                          AND t2.transaction_type_id = 1
                          LIMIT 1) as checkin_transaction_at'),
            ])
            ->orderBy('r.number')
            ->orderBy('tr.created_at')
            ->get();

        $shiftLog = null;
        if ($this->filterMode === 'shift' && $this->selectedShiftLogId) {
            $shiftLog = ShiftLog::find($this->selectedShiftLogId);
        }

        $dateFrom = $this->date_from ?? now()->toDateString();

        return $query->map(function ($row) use ($dateFrom, $shiftLog, $range) {
            // Calculate total excluding deposits (type 2) and cashouts (type 5)
            $effectiveAmount = $row->is_override ? (float) $row->paid_amount : (float) $row->payable_amount;
            $total = in_array($row->transaction_type_id, [2, 5]) ? 0 : $effectiveAmount;

            // Determine if guest is "Forwarded"
            $isForwarded = $this->isGuestForwarded($row, $shiftLog, $dateFrom);

            // Determine if guest checked out during this shift/range
            $isCheckedOut = $this->isGuestCheckedOut($row, $shiftLog, $range);

            // Determine display label for deposits based on remarks
            $displayType = $row->transaction_type;
            if ($row->transaction_type_id == 2) { // Deposit
                $remarks = strtolower($row->remarks ?? '');
                if (str_contains($remarks, 'room key') || str_contains($remarks, 'tv remote')) {
                    $displayType = 'Room Deposit';
                } else {
                    $displayType = 'Guest Deposit';
                }
            }

            return [
                'checkin_detail_id' => $row->checkin_detail_id,
                'room_number' => $row->room_number ?? '—',
                'room_id' => $row->room_id,
                'room_type' => $row->room_type ?? '—',
                'guest_name' => strtoupper($row->guest_name ?? '—'),
                'transaction_type' => $displayType ?? '—',
                'transaction_type_id' => $row->transaction_type_id,
                'check_in' => $row->check_in_at
                    ? Carbon::parse($row->check_in_at)->format('m-d-Y h:iA')
                    : '—',
                'check_out' => $row->check_out_at
                    ? Carbon::parse($row->check_out_at)->format('m-d-Y h:iA')
                    : '—',
                'hours_stayed' => $row->hours_stayed ? $row->hours_stayed . ' hrs' : '—',
                'amount' => $effectiveAmount,
                'remarks' => $row->remarks,
                'processed_by' => strtoupper($row->processed_by ?? '—'),
                'shift' => strtoupper($row->shift ?? '—'),
                'transaction_date' => $row->transaction_date
                    ? Carbon::parse($row->transaction_date)->format('m-d-Y h:iA')
                    : '—',
                'total' => $total,
                'is_forwarded' => $isForwarded,
                'is_checked_out' => $isCheckedOut,
                'is_override' => (bool) $row->is_override,
            ];
        })->toArray();
    }

    /**
     * Determine if a guest checked out during the current shift/range.
     */
    private function isGuestCheckedOut($row, ?ShiftLog $shiftLog, array $range): bool
    {
        if (!$row->check_out_at) {
            return false;
        }

        $checkOutAt = Carbon::parse($row->check_out_at);

        return $checkOutAt->between($range['start'], $range['end']);
    }

    /**
     * Get forwarded guest rows for shift mode.
     * These are guests who checked in BEFORE this shift started but are still occupying,
     * AND have no transactions in the current shift (otherwise their transactions already show).
     * Shows them as "FORWARDED" with the original frontdesk who checked them in.
     */
    private function getForwardedGuestRows(array $range): array
    {
        $shiftLog = ShiftLog::find($this->selectedShiftLogId);
        if (!$shiftLog) {
            return [];
        }

        $branchId = auth()->user()->branch_id;

        // Find previous shift to handle overlapping shifts
        // When shifts overlap (previous shift ends AFTER this shift starts),
        // guests checked out during the overlap belong to the previous shift, not this one
        $previousShift = ShiftLog::whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->where('time_in', '<', $shiftLog->time_in)
            ->orderBy('time_in', 'desc')
            ->first();
        $checkoutBoundary = ($previousShift && $previousShift->time_out > $shiftLog->time_in)
            ? $previousShift->time_out
            : $shiftLog->time_in;

        // Find guests who:
        // 1. Checked in BEFORE this shift started
        // 2. Haven't checked out yet OR checked out AFTER the checkout boundary
        //    (checkout boundary = max(shift.time_in, previousShift.time_out) to exclude overlap checkouts)
        $forwardedGuests = CheckinDetail::query()
            ->with(['guest', 'room.type', 'room.floor'])
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<', $shiftLog->time_in)
            ->where(function ($q) use ($checkoutBoundary) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>', $checkoutBoundary);
            })
            ->get();

        // Batch-load all transactions for forwarded guests in 1 query (fixes N+1)
        $forwardedIds = $forwardedGuests->pluck('id')->toArray();
        $allFwdTransactions = Transaction::whereIn('checkin_detail_id', $forwardedIds)
            ->with('shift_log.frontdesk')
            ->get()
            ->groupBy('checkin_detail_id');

        $rows = [];
        foreach ($forwardedGuests as $cd) {
            $cdTransactions = $allFwdTransactions->get($cd->id, collect());

            // Get the frontdesk who checked them in (from the check-in transaction)
            $checkinTransaction = $cdTransactions->where('transaction_type_id', 1)->first();

            // Get room key deposit (Room Key & TV Remote)
            $roomKeyDeposit = $cdTransactions
                ->where('transaction_type_id', 2)
                ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
                ->first();

            // Get total guest deposits (any deposit that is NOT room key) — only those before this shift
            $guestDepositTotal = (float) $cdTransactions
                ->where('transaction_type_id', 2)
                ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
                ->filter(fn($t) => $t->created_at < $shiftLog->time_in)
                ->sum('payable_amount');

            // Get total cashouts (type 5) for this checkin_detail — only those before this shift
            $totalCashouts = (float) $cdTransactions
                ->where('transaction_type_id', 5)
                ->filter(fn($t) => $t->created_at < $shiftLog->time_in)
                ->sum('payable_amount');

            $checkinFrontdesk = $checkinTransaction?->shift_log?->frontdesk?->name ?? '—';
            $roomCharge = (float) ($checkinTransaction?->payable_amount ?? 0);
            $roomKeyDepositAmount = (float) ($roomKeyDeposit?->payable_amount ?? 0);
            $guestDepositAmount = max(0, $guestDepositTotal - $totalCashouts);

            $checkInAt = $cd->check_in_at ? Carbon::parse($cd->check_in_at) : null;
            $checkOutAt = $cd->check_out_at ? Carbon::parse($cd->check_out_at) : null;

            // Add forwarded room row
            $rows[] = [
                'checkin_detail_id' => $cd->id,
                'room_number' => $cd->room?->number ?? '—',
                'room_id' => $cd->room_id,
                'room_type' => $cd->room?->type?->name ?? '—',
                'guest_name' => strtoupper($cd->guest?->name ?? '—'),
                'transaction_type' => 'FWD ROOM',
                'transaction_type_id' => 0, // Special ID for forwarded display
                'check_in' => $checkInAt?->format('m-d-Y h:iA') ?? '—',
                'check_out' => $checkOutAt?->format('m-d-Y h:iA') ?? '—',
                'hours_stayed' => $cd->hours_stayed ? $cd->hours_stayed . ' hrs' : '—',
                'amount' => $roomCharge,
                'remarks' => 'Room charge from previous shift',
                'processed_by' => strtoupper($checkinFrontdesk),
                'shift' => '—',
                'transaction_date' => '—',
                'total' => 0, // Don't add to totals (already counted in previous shift)
                'is_forwarded' => true,
                'is_forwarded_guest_row' => true,
            ];

            // Add forwarded room key deposit row if exists
            if ($roomKeyDepositAmount > 0) {
                $rows[] = [
                    'checkin_detail_id' => $cd->id,
                    'room_number' => $cd->room?->number ?? '—',
                    'room_id' => $cd->room_id,
                    'room_type' => $cd->room?->type?->name ?? '—',
                    'guest_name' => strtoupper($cd->guest?->name ?? '—'),
                    'transaction_type' => 'FWD ROOM DEPOSIT',
                    'transaction_type_id' => 0,
                    'check_in' => $checkInAt?->format('m-d-Y h:iA') ?? '—',
                    'check_out' => $checkOutAt?->format('m-d-Y h:iA') ?? '—',
                    'hours_stayed' => $cd->hours_stayed ? $cd->hours_stayed . ' hrs' : '—',
                    'amount' => $roomKeyDepositAmount,
                    'remarks' => 'Room key deposit from previous shift',
                    'processed_by' => strtoupper($checkinFrontdesk),
                    'shift' => '—',
                    'transaction_date' => '—',
                    'total' => 0,
                    'is_forwarded' => true,
                    'is_forwarded_guest_row' => true,
                ];
            }

            // Add forwarded guest deposit row if exists
            if ($guestDepositAmount > 0) {
                $rows[] = [
                    'checkin_detail_id' => $cd->id,
                    'room_number' => $cd->room?->number ?? '—',
                    'room_id' => $cd->room_id,
                    'room_type' => $cd->room?->type?->name ?? '—',
                    'guest_name' => strtoupper($cd->guest?->name ?? '—'),
                    'transaction_type' => 'FWD GUEST DEPOSIT',
                    'transaction_type_id' => 0,
                    'check_in' => $checkInAt?->format('m-d-Y h:iA') ?? '—',
                    'check_out' => $checkOutAt?->format('m-d-Y h:iA') ?? '—',
                    'hours_stayed' => $cd->hours_stayed ? $cd->hours_stayed . ' hrs' : '—',
                    'amount' => $guestDepositAmount,
                    'remarks' => 'Guest deposit from previous shift',
                    'processed_by' => strtoupper($checkinFrontdesk),
                    'shift' => '—',
                    'transaction_date' => '—',
                    'total' => 0,
                    'is_forwarded' => true,
                    'is_forwarded_guest_row' => true,
                ];
            }
        }

        // Find checked-out guests before this shift with unclaimed guest deposits
        // Limit to guests who checked out in last 7 days to prevent memory issues
        // Uses checkoutBoundary to also capture overlap guests (checked out between shift.time_in and prevShift.time_out)
        {
            $lookbackLimit = $shiftLog->time_in->copy()->subDays(7);
            $checkedOutGuests = CheckinDetail::query()
                ->with(['guest', 'room.type'])
                ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                ->where('check_in_at', '<', $shiftLog->time_in)
                ->whereNotNull('check_out_at')
                ->where('check_out_at', '>=', $lookbackLimit) // Only guests who checked out in last 14 days
                ->where('check_out_at', '<=', $checkoutBoundary)
                ->get();

            // Batch-load transactions for checked-out guests (fixes N+1)
            $checkedOutIds = $checkedOutGuests->pluck('id')->toArray();
            $allCheckedOutTransactions = Transaction::whereIn('checkin_detail_id', $checkedOutIds)
                ->whereIn('transaction_type_id', [2, 5])
                ->get()
                ->groupBy('checkin_detail_id');

            foreach ($checkedOutGuests as $cd) {
                $cdTransactions = $allCheckedOutTransactions->get($cd->id, collect());

                $guestDepositTotal = (float) $cdTransactions
                    ->where('transaction_type_id', 2)
                    ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
                    ->filter(fn($t) => $t->created_at < $shiftLog->time_in)
                    ->sum('payable_amount');

                $totalCashouts = (float) $cdTransactions
                    ->where('transaction_type_id', 5)
                    ->filter(fn($t) => $t->created_at < $shiftLog->time_in)
                    ->sum('payable_amount');

                $unclaimedAmount = max(0, $guestDepositTotal - $totalCashouts);

                if ($unclaimedAmount > 0) {
                    $checkInAt = $cd->check_in_at ? Carbon::parse($cd->check_in_at) : null;
                    $checkOutAt = $cd->check_out_at ? Carbon::parse($cd->check_out_at) : null;

                    $rows[] = [
                        'checkin_detail_id' => $cd->id,
                        'room_number' => $cd->room?->number ?? '—',
                        'room_id' => $cd->room_id,
                        'room_type' => $cd->room?->type?->name ?? '—',
                        'guest_name' => strtoupper($cd->guest?->name ?? '—'),
                        'transaction_type' => 'FWD GUEST DEPOSIT',
                        'transaction_type_id' => 0,
                        'check_in' => $checkInAt?->format('m-d-Y h:iA') ?? '—',
                        'check_out' => $checkOutAt?->format('m-d-Y h:iA') ?? '—',
                        'hours_stayed' => $cd->hours_stayed ? $cd->hours_stayed . ' hrs' : '—',
                        'amount' => $unclaimedAmount,
                        'remarks' => 'Unclaimed guest deposit from checked-out guest',
                        'processed_by' => '—',
                        'shift' => '—',
                        'transaction_date' => '—',
                        'total' => 0,
                        'is_forwarded' => true,
                        'is_forwarded_guest_row' => true,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Determine if a guest is forwarded based on filter mode.
     *
     * Shift mode: Guest is forwarded if their check-in transaction was created
     * BEFORE the selected shift's time_in.
     *
     * Date range mode: Guest is forwarded if their check-in date is before date_from.
     */
    private function isGuestForwarded($row, ?ShiftLog $shiftLog, string $dateFrom): bool
    {
        if ($this->filterMode === 'shift' && $shiftLog) {
            // Shift mode: Check if check-in transaction was before this shift started
            if (!$row->checkin_transaction_at) {
                return false;
            }

            $checkInTransactionTime = Carbon::parse($row->checkin_transaction_at);

            // Guest is forwarded if they checked in BEFORE this shift started
            if ($checkInTransactionTime >= $shiftLog->time_in) {
                return false; // Checked in during this shift
            }

            // Guest was still occupying when shift started
            $checkOutTime = $row->check_out_at ? Carbon::parse($row->check_out_at) : null;
            return $checkOutTime === null || $checkOutTime >= $shiftLog->time_in;
        }

        // Date range mode: use date comparison
        if (!$row->check_in_at) {
            return false;
        }

        return Carbon::parse($row->check_in_at)->toDateString() < $dateFrom;
    }

    /**
     * Build summary totals grouped by transaction type.
     */
    private function buildSummaryByType(): array
    {
        $grouped = collect($this->salesRows)->groupBy('transaction_type_id');

        $summary = [
            'room_charges' => 0,      // Type 1: Check In
            'extensions' => 0,        // Type 6: Extend
            'amenities' => 0,         // Type 8: Amenities
            'food' => 0,              // Type 9: Food and Beverages
            'damages' => 0,           // Type 4: Damage Charges
            'transfers' => 0,         // Type 7: Transfer Room
            'deposits' => 0,          // Type 2: Deposit (not counted in total)
            'room_deposits' => 0,     // Room Key/TV Remote deposits
            'guest_deposits' => 0,    // Other guest deposits
            'cashouts' => 0,          // Type 5: Cashout (not counted in total)
        ];

        foreach ($grouped as $typeId => $transactions) {
            $sum = collect($transactions)->sum('amount');

            switch ($typeId) {
                case 1:
                    $summary['room_charges'] = $sum;
                    break;
                case 2:
                    $summary['deposits'] = $sum;
                    break;
                case 4:
                    $summary['damages'] = $sum;
                    break;
                case 5:
                    $summary['cashouts'] = $sum;
                    break;
                case 6:
                    $summary['extensions'] = $sum;
                    break;
                case 7:
                    $summary['transfers'] = $sum;
                    break;
                case 8:
                    $summary['amenities'] = $sum;
                    break;
                case 9:
                    $summary['food'] = $sum;
                    break;
            }
        }

        // Calculate deposit breakdown (Room Deposit vs Guest Deposit)
        foreach ($this->salesRows as $row) {
            if ($row['transaction_type_id'] == 2) {
                $remarks = strtolower($row['remarks'] ?? '');
                if (str_contains($remarks, 'room key') || str_contains($remarks, 'tv remote')) {
                    $summary['room_deposits'] += $row['amount'];
                } else {
                    $summary['guest_deposits'] += $row['amount'];
                }
            }
        }

        // Grand total excludes deposits (type 2) and cashouts (type 5)
        $summary['grand_total'] = $summary['room_charges']
            + $summary['extensions']
            + $summary['amenities']
            + $summary['food']
            + $summary['damages']
            + $summary['transfers'];

        return $summary;
    }

    /**
     * Build expenses summary for the filter range.
     */
    private function buildExpensesSummary(): void
    {
        $range = $this->getFilterRange();

        $rows = Expense::query()
            ->with(['expenseCategory', 'user'])
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->when($this->filterMode === 'date_range' && $this->frontdesk, fn($q) => $q->where('user_id', $this->frontdesk))
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($e) {
                return [
                    'expense_type' => $e->expenseCategory?->name ?? '—',
                    'description' => $e->description ?? '—',
                    'shift' => $e->shift ?? '—',
                    'amount' => (float) $e->amount,
                    'frontdesk' => strtoupper($e->user?->name ?? '—'),
                ];
            });

        $this->expensesRows = $rows;
        $this->expensesTotal = (float) $rows->sum('amount');
    }

    /**
     * Build remittance summary for the filter range.
     */
    private function buildRemittanceSummary(): void
    {
        $range = $this->getFilterRange();

        $rows = Remittance::query()
            ->with('user')
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->when($this->filterMode === 'date_range' && $this->frontdesk, fn($q) => $q->where('user_id', $this->frontdesk))
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($r) {
                return [
                    'description' => $r->description ?? '—',
                    'amount' => (float) $r->total_remittance,
                    'frontdesk' => strtoupper($r->user?->name ?? '—'),
                ];
            });

        $this->remittanceRows = $rows;
        $this->remittanceTotal = (float) $rows->sum('amount');
    }

    /**
     * Build room summary by floor and transaction type.
     */
    private function buildRoomSummary(): void
    {
        $floors = Floor::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->orderBy('number')
            ->get();

        $occupyingIds = $this->getOccupyingCheckinIds();

        if (empty($occupyingIds)) {
            $this->roomSummary = [
                'floors' => $floors->map(fn($f) => ['id' => $f->id, 'number' => $f->number])->all(),
                'rows' => [],
                'totals' => [],
            ];
            return;
        }

        $range = $this->getFilterRange();

        $transactions = Transaction::query()
            ->with(['room.floor', 'transaction_type'])
            ->whereIn('checkin_detail_id', $occupyingIds)
            ->whereNotIn('transaction_type_id', [2, 5]) // Exclude deposits and cashouts
            // Filter transactions by range
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->when($this->filterMode === 'date_range' && $this->frontdesk, function ($q) {
                $q->whereHas('shift_log', fn($q2) => $q2->where('frontdesk_id', $this->frontdesk));
            })
            ->get();

        $grouped = $transactions->groupBy(
            fn($t) => $t->transaction_type?->name ?? 'Unknown'
        );

        $tableRows = [];
        $totalsPerFloor = [];

        foreach ($floors as $floor) {
            $totalsPerFloor[$floor->id] = 0;
        }

        foreach ($grouped as $typeName => $items) {
            $rowPerFloor = [];
            $rowTotal = 0;

            foreach ($floors as $floor) {
                $amount = (float) $items
                    ->filter(fn($t) => $t->room?->floor_id == $floor->id)
                    ->sum('payable_amount');

                $rowPerFloor[$floor->id] = $amount;
                $rowTotal += $amount;
                $totalsPerFloor[$floor->id] += $amount;
            }

            $tableRows[] = [
                'description' => $typeName === 'Check In' ? 'Room' : $typeName,
                'floors' => $rowPerFloor,
                'row_total' => $rowTotal,
            ];
        }

        $this->roomSummary = [
            'floors' => $floors->map(fn($f) => [
                'id' => $f->id,
                'number' => $f->number,
            ])->all(),
            'rows' => $tableRows,
            'totals' => $totalsPerFloor,
        ];
    }
}
