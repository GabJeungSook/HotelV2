<?php

namespace App\Http\Livewire\BackOffice;

use Livewire\Component;
use App\Models\ShiftLog;
use App\Models\Transaction;
use App\Models\CheckinDetail;
use App\Models\Expense;
use App\Models\Remittance;
use App\Support\ShiftResolver;
use App\Support\ShiftSessionGrouper;
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

        // Cap effective time_out at the next shift log's time_in to prevent
        // overlap when shifts haven't fully closed before the next one starts.
        $effectiveTimeOut = $timeOut->copy();
        $nextShiftLog = ShiftLog::where('branch_id', $branchId)
            ->whereNotIn('id', $logIds)
            ->where('time_in', '>=', $timeIn)
            ->where('time_in', '<', $timeOut)
            ->orderBy('time_in')
            ->first();

        if ($nextShiftLog && $nextShiftLog->time_in->lt($timeOut)) {
            $effectiveTimeOut = $nextShiftLog->time_in;
        }

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
            ->where('check_in_at', '<=', $effectiveTimeOut)
            ->where(function ($q) use ($timeIn) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>=', $timeIn);
            })
            ->pluck('id')
            ->toArray();

        // Exclude non-forwarded guests whose check-in transaction belongs to
        // a different session (handles bidirectional overlap between shifts).
        $newCheckinIds = CheckinDetail::whereIn('id', $occupyingIds)
            ->where('check_in_at', '>=', $timeIn)
            ->pluck('id')
            ->toArray();

        $foreignCheckinIds = empty($newCheckinIds) ? [] : Transaction::query()
            ->whereIn('checkin_detail_id', $newCheckinIds)
            ->where('transaction_type_id', 1)
            ->whereNotNull('shift_log_id')
            ->whereNotIn('shift_log_id', $logIds)
            ->pluck('checkin_detail_id')
            ->toArray();

        $occupyingIds = array_values(array_diff($occupyingIds, $foreignCheckinIds));

        // Attribute transactions by who actually processed them (this session's shift logs).
        // Also include transactions with NULL shift_log_id (e.g. kiosk check-ins) that
        // fall within the shift's time window, so deposits are not missed.
        $transactions = Transaction::whereIn('checkin_detail_id', $occupyingIds)
            ->where(function ($q) use ($logIds, $timeIn, $effectiveTimeOut) {
                $q->whereIn('shift_log_id', $logIds)
                  ->orWhere(function ($q2) use ($timeIn, $effectiveTimeOut) {
                      $q2->whereNull('shift_log_id')
                         ->whereBetween('created_at', [$timeIn, $effectiveTimeOut]);
                  });
            })
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

        // Expenses (attributed to this session's shift logs)
        $expenses = Expense::whereIn('shift_log_id', $logIds)->get();
        $totalExpenses = (float) $expenses->sum('amount');

        // Remittance (attributed to this session's shift logs)
        $remittances = Remittance::whereIn('shift_log_id', $logIds)->get();
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
            ->reject(fn($l) => $this->shiftTypeOf($l) === $currentShiftType
                             && $l->time_in->format('Y-m-d') === $currentShiftDate);

        if ($nextLogs->isEmpty()) {
            return '—';
        }

        // Get the first group (same shift type + date)
        $firstLog = $nextLogs->first();
        $shiftType = $this->shiftTypeOf($firstLog);
        $shiftDate = $firstLog->time_in->format('Y-m-d');

        return $nextLogs
            ->filter(fn($l) => $this->shiftTypeOf($l) === $shiftType && $l->time_in->format('Y-m-d') === $shiftDate)
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
            ->reject(fn($l) => $this->shiftTypeOf($l) === $currentShiftType
                             && $l->time_in->format('Y-m-d') === $currentShiftDate)
            ->first();

        if (!$prevLog) {
            return $default;
        }

        // Get all logs in same session (same shift type + date)
        $shiftType = $this->shiftTypeOf($prevLog);
        $shiftDate = $prevLog->time_in->format('Y-m-d');

        $prevLogs = ShiftLog::query()
            ->whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->whereNotNull('time_out')
            ->get()
            ->filter(function ($l) use ($shiftType, $shiftDate) {
                return $this->shiftTypeOf($l) === $shiftType
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
            ->reject(fn($l) => $this->shiftTypeOf($l) === $shiftType
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

        // --- Guest Deposit: use end-of-previous-shift balance (same per-guest approach as SalesReportV2) ---
        // This equals: forwarded into prev shift + prev shift deposits - prev shift cashouts,
        // but computed per-guest to avoid negative-balance guests dragging down the total.
        $guestDeposit = $this->calculateForwardedGuestDeposit($currentTimeIn, $branchId);

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
    /**
     * Calculate forwarded guest deposit using per-guest amounts.
     * Mirrors SalesReportV2::getForwardedGuestRows() exactly:
     * 1. Still-occupying forwarded guests (per-guest MAX(0, deposits - cashouts))
     * 2. Checked-out guests with unclaimed deposits (per-guest MAX(0, deposits - cashouts))
     * Loads ALL transactions then filters in PHP to match SalesReportV2 behavior.
     */
    private function calculateForwardedGuestDeposit(Carbon $currentTimeIn, int $branchId): float
    {
        // Find previous shift for overlap boundary (same logic as SalesReportV2)
        $previousShift = ShiftLog::whereHas('frontdesk', fn($q) => $q->where('branch_id', $branchId))
            ->where('time_in', '<', $currentTimeIn)
            ->orderBy('time_in', 'desc')
            ->first();
        $checkoutBoundary = ($previousShift && $previousShift->time_out > $currentTimeIn)
            ? $previousShift->time_out
            : $currentTimeIn;

        // 1. Still-occupying forwarded guests
        $forwardedGuests = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<', $currentTimeIn)
            ->where(function ($q) use ($checkoutBoundary) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>', $checkoutBoundary);
            })
            ->pluck('id')
            ->toArray();

        // 2. Checked-out guests with potential unclaimed deposits
        $checkedOutGuests = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<', $currentTimeIn)
            ->whereNotNull('check_out_at')
            ->where('check_out_at', '<=', $checkoutBoundary)
            ->pluck('id')
            ->toArray();

        $allGuestIds = array_unique(array_merge($forwardedGuests, $checkedOutGuests));

        if (empty($allGuestIds)) {
            return 0;
        }

        // Load ALL transactions (no time filter in SQL — filter in PHP to match SalesReportV2)
        $allTransactions = Transaction::whereIn('checkin_detail_id', $allGuestIds)
            ->whereIn('transaction_type_id', [2, 5])
            ->get()
            ->groupBy('checkin_detail_id');

        $total = 0;
        foreach ($allTransactions as $checkinId => $transactions) {
            // Guest deposits before this shift (exclude room key/tv remote)
            $guestDep = (float) $transactions->where('transaction_type_id', 2)
                ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
                ->filter(fn($t) => $t->created_at < $currentTimeIn)
                ->sum('payable_amount');

            // Cashouts before this shift
            $cashouts = (float) $transactions->where('transaction_type_id', 5)
                ->filter(fn($t) => $t->created_at < $currentTimeIn)
                ->sum('payable_amount');

            $total += max(0, $guestDep - $cashouts);
        }

        return $total;
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
            ->with('frontdesk:id,name')
            ->get();

        $orderedSessions = collect(ShiftSessionGrouper::group($allLogs))
            ->filter(fn ($s) => $s['time_in'] < $currentTimeIn)
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
            $prevOwnNs = $this->computeSessionNetSales($ti, $to, $branchId, $session['log_ids'] ?? []);
        }

        // Current shift's fb = last session's nsp + last session's fb
        return $lastNsp + $forwardedBalance;
    }

    private function getShiftType(Carbon $timeIn): string
    {
        return ShiftResolver::fromClock($timeIn);
    }

    private function shiftTypeOf(ShiftLog $log): string
    {
        return $log->shift ?? ShiftResolver::fromClock($log->time_in);
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
            ->get();

        $this->availableShiftSessions = ShiftSessionGrouper::group($shiftLogs);
    }

    /**
     * Compute net sales for a given shift session — shared by generateReport() and calculateForwardedBalance().
     * Uses the same overlap and unclaimed deposit logic as generateReport().
     */
    private function computeSessionNetSales(Carbon $timeIn, Carbon $timeOut, int $branchId, array $sessionLogIds = []): float
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

        // Gross sales — prefer attribution by this session's shift logs when available.
        $grossSales = (float) DB::table('transactions as tr')
            ->leftJoin('checkin_details as cd', 'cd.id', '=', 'tr.checkin_detail_id')
            ->whereIn('tr.checkin_detail_id', $occupyingIds)
            ->whereNotIn('tr.transaction_type_id', [2, 5])
            ->where(function ($q) use ($timeIn, $timeOut, $overlapCheckinIds, $sessionLogIds) {
                if (!empty($sessionLogIds)) {
                    $q->whereIn('tr.shift_log_id', $sessionLogIds);
                } else {
                    $q->whereBetween('tr.created_at', [$timeIn, $timeOut])
                      ->orWhere(fn($q2) => $q2->where('tr.transaction_type_id', 1)
                          ->whereBetween('cd.check_in_at', [$timeIn, $timeOut]));
                }
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

        $expenses = !empty($sessionLogIds)
            ? (float) Expense::whereIn('shift_log_id', $sessionLogIds)->sum('amount')
            : (float) Expense::whereBetween('created_at', [$timeIn, $timeOut])->sum('amount');

        return $grossSales - $expenses;
    }

    public function render()
    {
        return view('livewire.back-office.frontdesk-report-v2');
    }
}
