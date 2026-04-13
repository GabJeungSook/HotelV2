<?php

namespace App\Http\Livewire\BackOffice;

use Livewire\Component;
use App\Models\ShiftLog;
use App\Models\Transaction;
use App\Models\CheckinDetail;
use App\Models\Expense;
use App\Models\Remittance;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FrontdeskReportV2 extends Component
{
    public $weekStart = null;
    public $selectedShiftLogId;
    public array $availableShiftSessions = [];
    public array $reportData = [];

    public function mount()
    {
        $this->loadAvailableShiftSessions();
        if (!empty($this->availableShiftSessions)) {
            $this->selectedShiftLogId = end($this->availableShiftSessions)['id'];
        }
        $this->generateReport();
    }

    public function updatedSelectedShiftLogId()
    {
        $this->generateReport();
    }

    public function generateReport()
    {
        if (!$this->selectedShiftLogId) {
            $this->reportData = [];
            return;
        }

        $session = $this->getSelectedSession();
        if (!$session) {
            $this->reportData = [];
            return;
        }

        $logIds = $session['log_ids'];
        $shiftLogs = ShiftLog::whereIn('id', $logIds)->with('frontdesk:id,name')->get();

        // Use the same time range as SalesReportV2: load primary ShiftLog from DB
        $primaryShiftLog = ShiftLog::find($this->selectedShiftLogId);
        $timeIn = $primaryShiftLog->time_in;
        $timeOut = $primaryShiftLog->time_out;
        $branchId = auth()->user()->branch_id;

        // Opening Cash
        $openingCash = $this->calculateOpeningCash($shiftLogs);

        // Actual Cash (end_cash)
        $actualCash = $this->calculateActualCash($shiftLogs);

        // Frontdesk names
        $outgoingNames = $shiftLogs->map(fn($l) => $l->frontdesk?->name)->filter()->unique()->implode(', ');
        $incomingNames = $this->getIncomingFrontdesks($timeIn, $timeOut, $branchId);

        // Use occupying-guest approach (same as SalesReportV2) for accurate counts
        $occupyingIds = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<=', $timeOut)
            ->where(function ($q) use ($timeIn) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>=', $timeIn);
            })
            ->pluck('id')
            ->toArray();

        // All transactions for occupying guests within this shift's time range (same as SalesReportV2)
        $transactions = Transaction::whereIn('checkin_detail_id', $occupyingIds)
            ->whereBetween('created_at', [$timeIn, $timeOut])
            ->get();

        // Detect overlapping shifts and include overlap guests' room charges
        $overlapCheckinIds = [];
        $overlapRoomCharges = collect();
        $prevShiftForOverlapEarly = ShiftLog::whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->where('time_in', '<', $timeIn)
            ->orderBy('time_in', 'desc')
            ->first();
        if ($prevShiftForOverlapEarly && $prevShiftForOverlapEarly->time_out > $timeIn) {
            $overlapCheckinIds = CheckinDetail::whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                ->where('check_in_at', '<', $timeIn)
                ->whereBetween('check_out_at', [$timeIn, $prevShiftForOverlapEarly->time_out])
                ->pluck('id')
                ->toArray();
            if (!empty($overlapCheckinIds)) {
                $overlapRoomCharges = Transaction::whereIn('checkin_detail_id', $overlapCheckinIds)
                    ->where('transaction_type_id', 1)
                    ->get();
            }
        }

        // Sales Summary (Operation A)
        $checkins = $transactions->where('transaction_type_id', 1);
        $extensions = $transactions->where('transaction_type_id', 6);
        $transfers = $transactions->where('transaction_type_id', 7);
        $amenities = $transactions->where('transaction_type_id', 8);
        $food = $transactions->where('transaction_type_id', 9);
        $damages = $transactions->where('transaction_type_id', 4);
        $cashouts = $transactions->where('transaction_type_id', 5);
        $deposits = $transactions->where('transaction_type_id', 2);

        $roomDeposits = $deposits->filter(fn($t) => str_contains(strtolower($t->remarks ?? ''), 'room key') || str_contains(strtolower($t->remarks ?? ''), 'tv remote'));
        $guestDeposits = $deposits->filter(fn($t) => !str_contains(strtolower($t->remarks ?? ''), 'room key') && !str_contains(strtolower($t->remarks ?? ''), 'tv remote'));

        // Miscellaneous breakdown: amenities (type 8) + damages (type 4) + unclaimed deposits
        $amenitiesCount = $amenities->count();
        $amenitiesAmount = (float) $amenities->sum('payable_amount');
        $damagesCount = $damages->count();
        $damagesAmount = (float) $damages->sum('payable_amount');

        // Unclaimed guest deposits from previous shift (same logic as SalesReportV2)
        $unclaimedCount = 0;
        $unclaimedAmount = 0;
        $prevShiftLog = ShiftLog::whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->where('time_in', '<', $timeIn)
            ->orderBy('time_in', 'desc')
            ->first();
        if ($prevShiftLog) {
            $checkedOutGuests = CheckinDetail::query()
                ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                ->where('check_in_at', '<', $timeIn)
                ->where('check_out_at', '>=', $prevShiftLog->time_in)
                ->where('check_out_at', '<', $timeIn)
                ->pluck('id')
                ->toArray();

            if (!empty($checkedOutGuests)) {
                $checkedOutTransactions = Transaction::whereIn('checkin_detail_id', $checkedOutGuests)
                    ->whereIn('transaction_type_id', [2, 5])
                    ->get()
                    ->groupBy('checkin_detail_id');

                foreach ($checkedOutGuests as $cdId) {
                    $cdTxns = $checkedOutTransactions->get($cdId, collect());
                    $depTotal = (float) $cdTxns->where('transaction_type_id', 2)
                        ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
                        ->filter(fn($t) => $t->created_at < $timeIn)
                        ->sum('payable_amount');
                    $cashoutTotal = (float) $cdTxns->where('transaction_type_id', 5)
                        ->filter(fn($t) => $t->created_at < $timeIn)
                        ->sum('payable_amount');
                    $unclaimed = max(0, $depTotal - $cashoutTotal);
                    if ($unclaimed > 0) {
                        $unclaimedCount++;
                        $unclaimedAmount += $unclaimed;
                    }
                }
            }
        }

        $miscCount = $amenitiesCount + $damagesCount + $unclaimedCount;
        $miscAmount = $amenitiesAmount + $damagesAmount + $unclaimedAmount;

        $salesSummary = [
            'new_checkin' => ['count' => $checkins->count() + count($overlapCheckinIds), 'amount' => (float) $checkins->sum('payable_amount') + (float) $overlapRoomCharges->sum('payable_amount')],
            'extension' => ['count' => $extensions->count(), 'amount' => (float) $extensions->sum('payable_amount')],
            'transfer' => ['count' => $transfers->count(), 'amount' => (float) $transfers->sum('payable_amount')],
            'miscellaneous' => [
                'count' => $miscCount,
                'amount' => $miscAmount,
                'breakdown' => [
                    'amenities' => ['count' => $amenitiesCount, 'amount' => $amenitiesAmount],
                    'damages' => ['count' => $damagesCount, 'amount' => $damagesAmount],
                    'unclaimed' => ['count' => $unclaimedCount, 'amount' => $unclaimedAmount],
                ],
            ],
            'food' => ['count' => $food->count(), 'amount' => (float) $food->sum('payable_amount')],
            'drink' => ['count' => 0, 'amount' => 0],
            'others' => ['count' => 0, 'amount' => 0],
        ];
        $salesSummary['total'] = [
            'count' => collect($salesSummary)->sum('count'),
            'amount' => collect($salesSummary)->sum('amount'),
        ];

        // Gross Sales (excludes deposits type 2 and cashouts type 5) + overlap room charges + unclaimed deposits
        $grossSales = (float) $transactions->whereNotIn('transaction_type_id', [2, 5])->sum('payable_amount')
                    + (float) $overlapRoomCharges->sum('payable_amount')
                    + $unclaimedAmount;

        // Forwarded guests
        $forwarded = $this->getForwardedData($timeIn, $branchId);

        // Checkouts during this shift
        $checkoutDetails = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('check_out_at', [$timeIn, $timeOut])
            ->get();
        $checkoutCount = $checkoutDetails->count();

        // Checkout room deposit amount (returned deposits)
        $checkoutIds = $checkoutDetails->pluck('id')->toArray();
        $checkoutRoomDeposit = empty($checkoutIds) ? 0 : (float) Transaction::whereIn('checkin_detail_id', $checkoutIds)
            ->where('transaction_type_id', 2)
            ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');

        // Rooms still occupied at shift end — count + their room deposits
        $occupiedAtEnd = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<=', $timeOut)
            ->where(function ($q) use ($timeOut) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>', $timeOut);
            })
            ->pluck('id')
            ->toArray();

        $endShiftRoomDepositCount = count($occupiedAtEnd);
        $endShiftRoomDeposit = empty($occupiedAtEnd) ? 0 : (float) Transaction::whereIn('checkin_detail_id', $occupiedAtEnd)
            ->where('transaction_type_id', 2)
            ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');

        // Count unique guests still occupying at end of shift with guest deposits
        // Uses same boundary logic as getForwardedData() so counts match across shifts
        $occupiedAtEndForDeposits = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<', $timeOut)
            ->where(function ($q) use ($timeOut) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>=', $timeOut);
            })
            ->pluck('id')
            ->toArray();
        $endShiftGuestDepositCount = empty($occupiedAtEndForDeposits) ? 0 : (int) Transaction::whereIn('checkin_detail_id', $occupiedAtEndForDeposits)
            ->where('transaction_type_id', 2)
            ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
            ->where('created_at', '<', $timeOut)
            ->distinct('checkin_detail_id')
            ->count('checkin_detail_id');

        // Current shift guest deposits minus cashouts
        $currentGuestDeposit = max(0, (float) $guestDeposits->sum('payable_amount') - (float) $cashouts->sum('payable_amount'));

        // Expenses
        $expenses = Expense::whereBetween('created_at', [$timeIn, $timeOut])->get();
        $totalExpenses = (float) $expenses->sum('amount');

        // Remittance
        $remittances = Remittance::whereBetween('created_at', [$timeIn, $timeOut])->get();
        $totalRemittance = (float) $remittances->sum('total_remittance');

        // Net Sales
        $netSales = $grossSales - $totalExpenses;

        // Previous shift data for Cash Drawer
        $prevShiftData = $this->getPreviousShiftData($timeIn, $branchId);
        $forwardedBalance = $this->calculateForwardedBalance($timeIn, $branchId);
        $expectedReceived = $prevShiftData['net_sales'] + $prevShiftData['key_deposit'] + $prevShiftData['guest_deposit'] + $forwardedBalance;
        $cashDifference = $expectedReceived - $openingCash;

        // Legacy forwarded values still used in Room Status section
        $fwdRoomDeposit = $forwarded['room_deposit'];
        $fwdGuestDeposit = $forwarded['guest_deposit'];

        // Forwarded deposit summary values (computed early for cash reconciliation)
        $fwdDepRoomCount = max(0, $checkins->count() + $forwarded['room_count'] - $checkoutCount);
        // overlap will adjust these later, but we need base values now
        $fwdDepGuestAmount = max(0, (float) $guestDeposits->sum('payable_amount') + $this->calculateForwardedGuestDeposit($timeIn, $branchId) - (float) $cashouts->sum('payable_amount'));

        // Room Summary (Operation B)
        // Check-in count including overlap guests (reuse already-computed overlap data)
        $currentCheckinCount = $checkins->count() + count($overlapCheckinIds);
        $forwardedCount = $forwarded['room_count'] - count($overlapCheckinIds);
        $roomSummary = [
            'forwarded_prev' => ['count' => $forwardedCount, 'amount' => $prevShiftData['key_deposit']],
            'current_shift' => ['count' => $currentCheckinCount, 'amount' => $currentCheckinCount * 200],
        ];

        // Forwarded deposit summary (with overlap-adjusted counts)
        $fwdDepRoomAmount = max(0, $currentCheckinCount + $forwardedCount - $checkoutCount) * 200;
        $fwdDepSubtotal = $fwdDepRoomAmount + $fwdDepGuestAmount;

        // Cash Reconciliation: net sales prev + net sales current + forwarded deposit subtotal + forwarded balance
        $expectedCash = $prevShiftData['net_sales'] + $netSales + $fwdDepSubtotal + $forwardedBalance - $totalRemittance;
        $difference = $expectedCash - $actualCash;

        $this->reportData = [
            'frontdesk_outgoing' => $outgoingNames ?: '—',
            'frontdesk_incoming' => $incomingNames ?: '—',
            'shift_opened' => $shiftLogs->min('time_in')->format('F d, Y g:i A'),
            'shift_closed' => $shiftLogs->max('time_out')->format('F d, Y g:i A'),

            'cash_drawer' => [
                'net_sales_prev' => $prevShiftData['net_sales'],
                'key_deposit_prev' => $prevShiftData['key_deposit'],
                'guest_deposit_prev' => $prevShiftData['guest_deposit'],
                'forwarded_balance' => $forwardedBalance,
                'cash_received' => $openingCash,
                'expected_received' => $expectedReceived,
                'cash_difference' => $cashDifference,
                'has_previous' => $prevShiftData['has_previous'],
            ],

            'sales_summary' => $salesSummary,
            'room_summary' => $roomSummary,
            'room_summary_subtotal' => [
                'count' => $roomSummary['forwarded_prev']['count'] + $roomSummary['current_shift']['count'],
                'amount' => $roomSummary['forwarded_prev']['amount'] + $roomSummary['current_shift']['amount'],
            ],
            'guest_deposit_summary' => [
                'forwarded_prev' => [
                    'count' => $forwarded['guest_deposit_count'],
                    'amount' => $prevShiftData['guest_deposit'],
                ],
                'current_shift' => [
                    'count' => $guestDeposits->unique('checkin_detail_id')->count(),
                    'amount' => (float) $guestDeposits->sum('payable_amount'),
                ],
            ],
            'guest_deposit_subtotal' => [
                'count' => $forwarded['guest_deposit_count'] + $guestDeposits->unique('checkin_detail_id')->count(),
                'amount' => $prevShiftData['guest_deposit'] + (float) $guestDeposits->sum('payable_amount'),
            ],
            'checkout_summary' => [
                'count' => $checkoutCount,
                'amount' => $checkoutRoomDeposit,
            ],
            'forwarded_deposit_summary' => [
                'room_deposit' => [
                    'count' => max(0, $currentCheckinCount + $forwardedCount - $checkoutCount),
                    'amount' => max(0, $currentCheckinCount + $forwardedCount - $checkoutCount) * 200,
                ],
                'guest_deposit' => [
                    'count' => $endShiftGuestDepositCount,
                    'amount' => max(0, (float) $guestDeposits->sum('payable_amount') + $this->calculateForwardedGuestDeposit($timeIn, $branchId) - (float) $cashouts->sum('payable_amount')),
                ],
            ],

            'final_sales' => [
                'gross_sales' => $grossSales,
                'refund' => 0,
                'expenses' => $totalExpenses,
                'discounts' => 0,
                'net_sales' => $netSales,
            ],

            'cash_position' => [
                'opening_cash' => $openingCash,
                'forwarded_balance' => $prevShiftData['net_sales'],
                'net_sales' => $netSales,
                'remittance' => $totalRemittance,
            ],

            'cash_reconciliation' => [
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'difference' => $difference,
            ],
        ];
    }

    private function getSelectedSession(): ?array
    {
        return collect($this->availableShiftSessions)
            ->firstWhere('id', $this->selectedShiftLogId);
    }

    private function calculateOpeningCash($shiftLogs): float
    {
        $values = $shiftLogs->pluck('beginning_cash')->filter(fn($v) => $v !== null);

        if ($values->unique()->count() <= 1) {
            return (float) $values->first();
        }

        // Combined shift: if one is 1.00, add it to the other
        $main = $values->filter(fn($v) => (float) $v != 1.0)->first() ?? 0;
        $sub = $values->filter(fn($v) => (float) $v == 1.0)->sum();
        return (float) $main + (float) $sub;
    }

    private function calculateActualCash($shiftLogs): float
    {
        $values = $shiftLogs->pluck('end_cash')->filter(fn($v) => $v !== null);

        if ($values->unique()->count() <= 1) {
            return (float) $values->first();
        }

        $main = $values->filter(fn($v) => (float) $v != 1.0)->first() ?? 0;
        $sub = $values->filter(fn($v) => (float) $v == 1.0)->sum();
        return (float) $main + (float) $sub;
    }

    private function getIncomingFrontdesks(Carbon $currentTimeIn, Carbon $currentTimeOut, int $branchId): string
    {
        // Determine current shift session (type + date) to exclude it
        $currentShiftType = $this->getShiftType($currentTimeIn);
        $currentShiftDate = $currentTimeIn->format('Y-m-d');

        // Search from current shift's start time (not end time) to catch overlapping shifts,
        // then exclude logs from the same session
        $nextLogs = ShiftLog::query()
            ->whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->where('time_in', '>=', $currentTimeIn)
            ->orderBy('time_in', 'asc')
            ->with('frontdesk:id,name')
            ->limit(10)
            ->get()
            ->reject(fn($l) => $this->getShiftType($l->time_in) === $currentShiftType
                             && $l->time_in->format('Y-m-d') === $currentShiftDate);

        if ($nextLogs->isEmpty()) {
            return '—';
        }

        // Get the first group (same shift type + date)
        $firstLog = $nextLogs->first();
        $shiftType = $this->getShiftType($firstLog->time_in);
        $shiftDate = $firstLog->time_in->format('Y-m-d');

        return $nextLogs
            ->filter(fn($l) => $this->getShiftType($l->time_in) === $shiftType && $l->time_in->format('Y-m-d') === $shiftDate)
            ->map(fn($l) => $l->frontdesk?->name)
            ->filter()
            ->unique()
            ->implode(', ') ?: '—';
    }

    private function getForwardedData(Carbon $shiftTimeIn, int $branchId): array
    {
        // Guests who checked in BEFORE this shift and are still occupying
        $forwardedGuests = CheckinDetail::query()
            ->with(['guest', 'room.type'])
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<', $shiftTimeIn)
            ->where(function ($q) use ($shiftTimeIn) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>=', $shiftTimeIn);
            })
            ->get();

        $forwardedIds = $forwardedGuests->pluck('id')->toArray();

        if (empty($forwardedIds)) {
            return [
                'room_count' => 0,
                'room_amount' => 0,
                'room_deposit' => 0,
                'guest_deposit' => 0,
                'guest_deposit_count' => 0,
            ];
        }

        $allTransactions = Transaction::whereIn('checkin_detail_id', $forwardedIds)->get();

        // Room charges from forwarded guests
        $roomCharges = $allTransactions->where('transaction_type_id', 1);
        $roomAmount = (float) $roomCharges->sum('payable_amount');

        // Room deposits (key/remote)
        $roomDeposit = (float) $allTransactions
            ->where('transaction_type_id', 2)
            ->filter(fn($t) => $t->remarks === 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');

        // Guest deposits (non-room-key, before this shift)
        $guestDepositTotal = (float) $allTransactions
            ->where('transaction_type_id', 2)
            ->filter(fn($t) => $t->remarks !== 'Deposit From Check In (Room Key & TV Remote)')
            ->filter(fn($t) => $t->created_at < $shiftTimeIn)
            ->sum('payable_amount');

        // Cashouts before this shift
        $cashouts = (float) $allTransactions
            ->where('transaction_type_id', 5)
            ->filter(fn($t) => $t->created_at < $shiftTimeIn)
            ->sum('payable_amount');

        $guestDeposit = max(0, $guestDepositTotal - $cashouts);

        // Count guests with guest deposits
        $guestDepositCount = $allTransactions
            ->where('transaction_type_id', 2)
            ->filter(fn($t) => $t->remarks !== 'Deposit From Check In (Room Key & TV Remote)')
            ->filter(fn($t) => $t->created_at < $shiftTimeIn)
            ->pluck('checkin_detail_id')
            ->unique()
            ->count();

        return [
            'room_count' => $forwardedGuests->count(),
            'room_amount' => $roomAmount,
            'room_deposit' => $roomDeposit,
            'guest_deposit' => $guestDeposit,
            'guest_deposit_count' => $guestDepositCount,
        ];
    }

    private function getPreviousShiftData(Carbon $currentTimeIn, int $branchId): array
    {
        $default = ['net_sales' => 0, 'key_deposit' => 0, 'guest_deposit' => 0, 'has_previous' => false];

        // Determine current shift session to exclude it
        $currentShiftType = $this->getShiftType($currentTimeIn);
        $currentShiftDate = $currentTimeIn->format('Y-m-d');

        // Find the previous shift session by time_in (not time_out) to handle overlaps
        $prevLog = ShiftLog::query()
            ->whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->whereNotNull('time_out')
            ->where('time_in', '<', $currentTimeIn)
            ->orderBy('time_in', 'desc')
            ->get()
            ->reject(fn($l) => $this->getShiftType($l->time_in) === $currentShiftType
                             && $l->time_in->format('Y-m-d') === $currentShiftDate)
            ->first();

        if (!$prevLog) {
            return $default;
        }

        // Get all logs in same session (same shift type + date)
        $shiftType = $this->getShiftType($prevLog->time_in);
        $shiftDate = $prevLog->time_in->format('Y-m-d');

        $prevLogs = ShiftLog::query()
            ->whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->whereNotNull('time_out')
            ->get()
            ->filter(function ($l) use ($shiftType, $shiftDate) {
                return $this->getShiftType($l->time_in) === $shiftType
                    && $l->time_in->format('Y-m-d') === $shiftDate;
            });

        $prevTimeIn = $prevLogs->min('time_in');
        $prevTimeOut = $prevLogs->max('time_out');

        // --- Net Sales (gross sales - expenses from previous shift) ---
        $prevOccupyingIds = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<=', $prevTimeOut)
            ->where(fn($q) => $q->whereNull('check_out_at')->orWhere('check_out_at', '>=', $prevTimeIn))
            ->pluck('id')
            ->toArray();

        // Find overlap guests for the previous shift (same logic as SalesReportV2)
        $overlapCheckinIds = [];
        $shiftBeforePrev = ShiftLog::whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->whereNotNull('time_out')
            ->where('time_in', '<', $prevTimeIn)
            ->orderBy('time_in', 'desc')
            ->get()
            ->reject(fn($l) => $this->getShiftType($l->time_in) === $shiftType
                             && $l->time_in->format('Y-m-d') === $shiftDate)
            ->first();
        if ($shiftBeforePrev && $shiftBeforePrev->time_out > $prevTimeIn) {
            $overlapCheckinIds = CheckinDetail::whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                ->where('check_in_at', '<', $prevTimeIn)
                ->whereBetween('check_out_at', [$prevTimeIn, $shiftBeforePrev->time_out])
                ->pluck('id')
                ->toArray();
        }

        $grossSales = empty($prevOccupyingIds) ? 0 : (float) DB::table('transactions as tr')
            ->leftJoin('checkin_details as cd', 'cd.id', '=', 'tr.checkin_detail_id')
            ->whereIn('tr.checkin_detail_id', $prevOccupyingIds)
            ->whereNotIn('tr.transaction_type_id', [2, 5])
            ->where(function ($q) use ($prevTimeIn, $prevTimeOut, $overlapCheckinIds) {
                $q->whereBetween('tr.created_at', [$prevTimeIn, $prevTimeOut])
                  ->orWhere(fn($q2) => $q2->where('tr.transaction_type_id', 1)
                      ->whereBetween('cd.check_in_at', [$prevTimeIn, $prevTimeOut]));
                if (!empty($overlapCheckinIds)) {
                    $q->orWhere(fn($q3) => $q3->where('tr.transaction_type_id', 1)
                        ->whereIn('tr.checkin_detail_id', $overlapCheckinIds));
                }
            })
            ->sum('tr.payable_amount');

        // Unclaimed deposits from guests who checked out before prev shift but after the shift before it
        $prevUnclaimedAmount = 0;
        if ($shiftBeforePrev) {
            $prevCheckedOutGuests = CheckinDetail::query()
                ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                ->where('check_in_at', '<', $prevTimeIn)
                ->where('check_out_at', '>=', $shiftBeforePrev->time_in)
                ->where('check_out_at', '<', $prevTimeIn)
                ->pluck('id')
                ->toArray();

            if (!empty($prevCheckedOutGuests)) {
                $prevCheckedOutTxns = Transaction::whereIn('checkin_detail_id', $prevCheckedOutGuests)
                    ->whereIn('transaction_type_id', [2, 5])
                    ->get()
                    ->groupBy('checkin_detail_id');

                foreach ($prevCheckedOutGuests as $cdId) {
                    $cdTxns = $prevCheckedOutTxns->get($cdId, collect());
                    $depTotal = (float) $cdTxns->where('transaction_type_id', 2)
                        ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
                        ->filter(fn($t) => $t->created_at < $prevTimeIn)
                        ->sum('payable_amount');
                    $cashoutTotal = (float) $cdTxns->where('transaction_type_id', 5)
                        ->filter(fn($t) => $t->created_at < $prevTimeIn)
                        ->sum('payable_amount');
                    $unclaimed = max(0, $depTotal - $cashoutTotal);
                    $prevUnclaimedAmount += $unclaimed;
                }
            }
        }
        $grossSales += $prevUnclaimedAmount;

        $prevExpenses = (float) Expense::whereBetween('created_at', [$prevTimeIn, $prevTimeOut])->sum('amount');
        $netSales = $grossSales - $prevExpenses;

        // --- Key Deposit (remaining room deposit = guests still occupying at prev shift end × 200) ---
        $remainingAtPrevEnd = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<=', $prevTimeOut)
            ->where(fn($q) => $q->whereNull('check_out_at')->orWhere('check_out_at', '>', $prevTimeOut))
            ->count();
        $keyDeposit = $remainingAtPrevEnd * 200;

        // --- Guest Deposit: forwarded into prev shift + prev shift own deposits - prev shift cashouts ---
        $fwdGuestDepAtPrevStart = $this->calculateForwardedGuestDeposit($prevTimeIn, $branchId);

        $prevGuestDeposits = empty($prevOccupyingIds) ? 0 : (float) Transaction::whereIn('checkin_detail_id', $prevOccupyingIds)
            ->whereBetween('created_at', [$prevTimeIn, $prevTimeOut])
            ->where('transaction_type_id', 2)
            ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');

        $prevCashouts = empty($prevOccupyingIds) ? 0 : (float) Transaction::whereIn('checkin_detail_id', $prevOccupyingIds)
            ->whereBetween('created_at', [$prevTimeIn, $prevTimeOut])
            ->where('transaction_type_id', 5)
            ->sum('payable_amount');

        $guestDeposit = max(0, $fwdGuestDepAtPrevStart + $prevGuestDeposits - $prevCashouts);

        return [
            'net_sales' => $netSales,
            'key_deposit' => $keyDeposit,
            'guest_deposit' => $guestDeposit,
            'has_previous' => true,
        ];
    }

    /**
     * Calculate forwarded guest deposit using cumulative chain walk (same as SalesReportV2).
     */
    private function calculateForwardedGuestDeposit(Carbon $currentTimeIn, int $branchId): float
    {
        $allLogs = ShiftLog::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('time_out')
            ->orderBy('time_in', 'asc')
            ->get();

        // Group into sessions by shift type + date
        $sessions = [];
        foreach ($allLogs as $log) {
            $shiftType = $this->getShiftType($log->time_in);
            $shiftDate = $log->time_in->format('Y-m-d');
            $key = $shiftType . '_' . $shiftDate;

            if (!isset($sessions[$key])) {
                $sessions[$key] = ['time_in' => $log->time_in, 'time_out' => $log->time_out];
            }
            if ($log->time_in < $sessions[$key]['time_in']) {
                $sessions[$key]['time_in'] = $log->time_in;
            }
            if ($log->time_out > $sessions[$key]['time_out']) {
                $sessions[$key]['time_out'] = $log->time_out;
            }
        }

        $orderedSessions = collect($sessions)
            ->sortBy('time_in')
            ->filter(fn($s) => $s['time_in'] < $currentTimeIn)
            ->values();

        if ($orderedSessions->isEmpty()) {
            return 0;
        }

        $runningGuestDeposit = 0;

        foreach ($orderedSessions as $session) {
            $ti = $session['time_in'];
            $to = $session['time_out'];

            $occupyingIds = CheckinDetail::query()
                ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                ->where('check_in_at', '<=', $to)
                ->where(fn($q) => $q->whereNull('check_out_at')->orWhere('check_out_at', '>=', $ti))
                ->pluck('id')
                ->toArray();

            if (empty($occupyingIds)) {
                continue;
            }

            $transactions = Transaction::whereIn('checkin_detail_id', $occupyingIds)
                ->whereBetween('created_at', [$ti, $to])
                ->get();

            $ownGuestDep = (float) $transactions->where('transaction_type_id', 2)
                ->filter(fn($t) => !str_contains(strtolower($t->remarks ?? ''), 'room key') && !str_contains(strtolower($t->remarks ?? ''), 'tv remote'))
                ->sum('payable_amount');

            $ownCashouts = (float) $transactions->where('transaction_type_id', 5)->sum('payable_amount');

            $runningGuestDeposit = max(0, $runningGuestDeposit + $ownGuestDep - $ownCashouts);
        }

        return $runningGuestDeposit;
    }

    /**
     * Calculate forwarded balance using cumulative chain.
     * Shift 1: forwarded_balance = beginning_cash
     * Shift N: forwarded_balance = prev_shift.net_sales_prev + prev_shift.forwarded_balance
     */
    private function calculateForwardedBalance(Carbon $currentTimeIn, int $branchId): float
    {
        $allLogs = ShiftLog::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('time_out')
            ->orderBy('time_in', 'asc')
            ->get();

        // Group into sessions by shift type + date
        $sessions = [];
        foreach ($allLogs as $log) {
            $shiftType = $this->getShiftType($log->time_in);
            $shiftDate = $log->time_in->format('Y-m-d');
            $key = $shiftType . '_' . $shiftDate;

            if (!isset($sessions[$key])) {
                $sessions[$key] = [
                    'time_in' => $log->time_in,
                    'time_out' => $log->time_out,
                    'log_ids' => [],
                ];
            }
            $sessions[$key]['log_ids'][] = $log->id;
            if ($log->time_in < $sessions[$key]['time_in']) {
                $sessions[$key]['time_in'] = $log->time_in;
            }
            if ($log->time_out > $sessions[$key]['time_out']) {
                $sessions[$key]['time_out'] = $log->time_out;
            }
        }

        $orderedSessions = collect($sessions)
            ->sortBy('time_in')
            ->filter(fn($s) => $s['time_in'] < $currentTimeIn)
            ->values();

        if ($orderedSessions->isEmpty()) {
            // First shift: forwarded balance = beginning_cash
            $currentLogs = ShiftLog::where('branch_id', $branchId)
                ->whereNotNull('time_out')
                ->where('time_in', '>=', $currentTimeIn)
                ->orderBy('time_in', 'asc')
                ->limit(5)
                ->get();
            return (float) ($currentLogs->first()?->beginning_cash ?? 0);
        }

        // Walk through sessions computing forwarded_balance for each
        // fb(0) = beginning_cash
        // fb(N) = nsp(N-1) + fb(N-1), where nsp(N) = own_net_sales of session N-1
        $forwardedBalance = 0;
        $lastNsp = 0;       // nsp of the last session processed (net_sales_prev = own_ns of session before it)
        $prevOwnNs = 0;     // own net sales of the previous session (becomes nsp for the next)

        foreach ($orderedSessions as $index => $session) {
            $ti = $session['time_in'];
            $to = $session['time_out'];

            // nsp for this session = own net sales of the session before it
            $nsp = ($index === 0) ? 0 : $prevOwnNs;

            if ($index === 0) {
                // First session: forwarded_balance = beginning_cash
                $sessionLogs = ShiftLog::whereIn('id', $session['log_ids'])->get();
                $forwardedBalance = $this->calculateOpeningCash($sessionLogs);
            } else {
                // fb(N) = nsp(N-1) + fb(N-1)
                $forwardedBalance = $lastNsp + $forwardedBalance;
            }

            $lastNsp = $nsp;

            // Compute this session's own net sales using the same logic as generateReport()
            $prevOwnNs = $this->computeSessionNetSales($ti, $to, $branchId);
        }

        // Current shift's fb = last session's nsp + last session's fb
        return $lastNsp + $forwardedBalance;
    }

    private function getShiftType(Carbon $timeIn): string
    {
        $hour = $timeIn->hour;
        return ($hour >= 6 && $hour < 20) ? 'AM' : 'PM';
    }

    private function loadAvailableShiftSessions(): void
    {
        $weekStart = $this->weekStart ? Carbon::parse($this->weekStart)->startOfWeek() : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $shiftLogs = ShiftLog::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->whereNotNull('time_out')
            ->whereBetween('time_in', [$weekStart, $weekEnd])
            ->with('frontdesk:id,name')
            ->orderBy('time_in', 'asc')
            ->get();

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

            if ($log->time_in < $sessions[$key]['time_in']) {
                $sessions[$key]['time_in'] = $log->time_in;
            }
            if ($log->time_out > $sessions[$key]['time_out']) {
                $sessions[$key]['time_out'] = $log->time_out;
            }
        }

        $this->availableShiftSessions = collect($sessions)
            ->sortBy('time_in')
            ->map(function ($s) {
                $frontdeskNames = implode(', ', array_unique($s['frontdesks']));

                return [
                    'id' => $s['log_ids'][0],
                    'log_ids' => $s['log_ids'],
                    'label' => $s['shift_type'] . ' ' . $s['time_in']->format('M j')
                             . ' - ' . $frontdeskNames
                             . ' (' . $s['time_in']->format('g:i A') . ' - ' . $s['time_out']->format('g:i A') . ')',
                    'frontdesks' => $frontdeskNames,
                    'time_in' => $s['time_in'],
                    'time_out' => $s['time_out'],
                    'time_in_formatted' => $s['time_in']->format('F d, Y g:i A'),
                    'time_out_formatted' => $s['time_out']->format('F d, Y g:i A'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Compute net sales for a given shift session — shared by generateReport() and calculateForwardedBalance().
     * Uses the same overlap and unclaimed deposit logic as generateReport().
     */
    private function computeSessionNetSales(Carbon $timeIn, Carbon $timeOut, int $branchId): float
    {
        $occupyingIds = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<=', $timeOut)
            ->where(fn($q) => $q->whereNull('check_out_at')->orWhere('check_out_at', '>=', $timeIn))
            ->pluck('id')
            ->toArray();

        if (empty($occupyingIds)) {
            return 0;
        }

        // Detect overlap using individual ShiftLog (same as generateReport)
        $overlapCheckinIds = [];
        $prevShiftLog = ShiftLog::whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->where('time_in', '<', $timeIn)
            ->orderBy('time_in', 'desc')
            ->first();
        if ($prevShiftLog && $prevShiftLog->time_out > $timeIn) {
            $overlapCheckinIds = CheckinDetail::whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                ->where('check_in_at', '<', $timeIn)
                ->whereBetween('check_out_at', [$timeIn, $prevShiftLog->time_out])
                ->pluck('id')
                ->toArray();
        }

        // Gross sales (same query structure as generateReport)
        $grossSales = (float) DB::table('transactions as tr')
            ->leftJoin('checkin_details as cd', 'cd.id', '=', 'tr.checkin_detail_id')
            ->whereIn('tr.checkin_detail_id', $occupyingIds)
            ->whereNotIn('tr.transaction_type_id', [2, 5])
            ->where(function ($q) use ($timeIn, $timeOut, $overlapCheckinIds) {
                $q->whereBetween('tr.created_at', [$timeIn, $timeOut])
                  ->orWhere(fn($q2) => $q2->where('tr.transaction_type_id', 1)
                      ->whereBetween('cd.check_in_at', [$timeIn, $timeOut]));
                if (!empty($overlapCheckinIds)) {
                    $q->orWhere(fn($q3) => $q3->where('tr.transaction_type_id', 1)
                        ->whereIn('tr.checkin_detail_id', $overlapCheckinIds));
                }
            })
            ->sum('tr.payable_amount');

        // Unclaimed guest deposits (same logic as generateReport)
        if ($prevShiftLog) {
            $checkedOutGuests = CheckinDetail::query()
                ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
                ->where('check_in_at', '<', $timeIn)
                ->where('check_out_at', '>=', $prevShiftLog->time_in)
                ->where('check_out_at', '<', $timeIn)
                ->pluck('id')
                ->toArray();

            if (!empty($checkedOutGuests)) {
                $checkedOutTxns = Transaction::whereIn('checkin_detail_id', $checkedOutGuests)
                    ->whereIn('transaction_type_id', [2, 5])
                    ->get()
                    ->groupBy('checkin_detail_id');

                foreach ($checkedOutGuests as $cdId) {
                    $cdTxns = $checkedOutTxns->get($cdId, collect());
                    $depTotal = (float) $cdTxns->where('transaction_type_id', 2)
                        ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
                        ->filter(fn($t) => $t->created_at < $timeIn)
                        ->sum('payable_amount');
                    $cashoutTotal = (float) $cdTxns->where('transaction_type_id', 5)
                        ->filter(fn($t) => $t->created_at < $timeIn)
                        ->sum('payable_amount');
                    $unclaimed = max(0, $depTotal - $cashoutTotal);
                    $grossSales += $unclaimed;
                }
            }
        }

        $expenses = (float) Expense::whereBetween('created_at', [$timeIn, $timeOut])->sum('amount');

        return $grossSales - $expenses;
    }

    public function render()
    {
        return view('livewire.back-office.frontdesk-report-v2');
    }
}
