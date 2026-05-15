<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use App\Models\ShiftLog;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\CheckinDetail;
use App\Models\NewGuestReport;
use App\Models\ExtendedGuestReport;
use App\Models\CleaningHistory;
use App\Models\Expense;
use App\Models\PaymentOnShort;
use App\Models\Branch;
use App\Models\FrontdeskMenu;
use App\Models\StockMovement;
use App\Support\ShiftResolver;
use Carbon\Carbon;

class BigBossReport extends Component
{
    public $weekStart = null;
    public $selectedShiftLogId;
    public array $availableShiftSessions = [];

    public function mount()
    {
        $this->loadAvailableShiftSessions();
        if (!empty($this->availableShiftSessions)) {
            $this->selectedShiftLogId = end($this->availableShiftSessions)['id'];
        }
    }

    public function updatedSelectedShiftLogId()
    {
        // triggers re-render
    }

    public function exportHtml()
    {
        $session = $this->getSelectedSession();
        if (!$session) {
            return;
        }

        $reportData = $this->generateReport($session);
        $branch = Branch::find(auth()->user()->branch_id);
        $branchName = $branch->name ?? 'Branch';

        // Calculate gross total for NET INCOME
        $grossTotal = 0;
        foreach ($reportData['summaryRows'] as $row) {
            if ($row['label'] === 'GROSS TOTAL') {
                $grossTotal = $row['total'];
                break;
            }
        }

        $html = view('livewire.back-office.reports.big-boss-report-export', array_merge(
            [
                'selectedSession' => $session,
                'branchName' => $branchName,
                'grossTotal' => $grossTotal,
            ],
            $reportData
        ))->render();

        $shiftDate = $session['shift_date'] ?? now()->format('Y-m-d');
        $shiftType = $session['shift_type'] ?? 'AM';
        $filename = "{$branchName} {$shiftDate} {$shiftType} SHIFT.html";

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    public function render()
    {
        $session = $this->getSelectedSession();
        $reportData = $this->generateReport($session);

        return view('livewire.back-office.reports.big-boss-report', array_merge(
            ['selectedSession' => $session],
            $reportData
        ));
    }

    private function generateReport(?array $session): array
    {
        $empty = [
            'floors' => collect(),
            'summaryRows' => [],
            'totalNewGuest' => 0,
            'totalExtendedGuest' => 0,
            'totalUnoccupiedRooms' => 0,
            'unoccupiedRoomNumbers' => '',
            'cleaningOrder' => '',
            'maintenanceRooms' => '',
            'expenses' => collect(),
            'expensesTotal' => 0,
            'paymentOnShorts' => collect(),
            'paymentOnShortsTotal' => 0,
            'frontdeskChart' => [],
            'roomCleaningChart' => [],
            'roomboyLogs' => [],
            'stocksInventory' => [],
            'stocksSummary' => [],
        ];

        if (!$session) {
            return $empty;
        }

        $branchId = auth()->user()->branch_id;
        $timeIn = Carbon::parse($session['time_in']);
        $timeOut = Carbon::parse($session['time_out']);
        $logIds = $session['log_ids'] ?? [];

        // Pre-fetch menu_id → parent category name map for splitting FOODS vs DRINKS
        $menuParentMap = FrontdeskMenu::where('branch_id', $branchId)
            ->with('frontdeskCategory.parentCategory')
            ->get()
            ->mapWithKeys(fn ($m) => [$m->id => strtoupper($m->frontdeskCategory?->parentCategory?->name ?? '')])
            ->toArray();

        // Pre-fetch 6-hour base rates for all room types (keyed by type_id) - used for penalties
        $sixHourStaying = \App\Models\StayingHour::where('branch_id', $branchId)->where('number', 6)->first();
        $baseRatesByType = $sixHourStaying
            ? \App\Models\Rate::where('branch_id', $branchId)
                ->where('staying_hour_id', $sixHourStaying->id)
                ->pluck('amount', 'type_id')
                ->toArray()
            : [];

        $floors = Floor::where('branch_id', $branchId)->orderBy('number')->get();
        $allRooms = Room::where('branch_id', $branchId)->with(['floor', 'type'])->get();

        // Occupying guest IDs during this shift
        $occupyingDetails = CheckinDetail::query()
            ->whereHas('room', fn($q) => $q->where('branch_id', $branchId))
            ->where('check_in_at', '<=', $timeOut)
            ->where(function ($q) use ($timeIn) {
                $q->whereNull('check_out_at')
                  ->orWhere('check_out_at', '>=', $timeIn);
            })
            ->with(['guest', 'room.floor', 'room.type', 'rate.stayingHour'])
            ->get();

        // Remove duplicate checkin_details (same guest, room, and check_in_at)
        $occupyingDetails = $occupyingDetails->unique(function ($detail) {
            return $detail->guest_id . '-' . $detail->room_id . '-' . $detail->check_in_at;
        });

        $occupyingIds = $occupyingDetails->pluck('id')->toArray();
        $occupiedRoomIds = $occupyingDetails->pluck('room_id')->unique()->toArray();

        // Attribute transactions by who actually processed them (this session's shift logs).
        $transactions = empty($occupyingIds) ? collect() : Transaction::query()
            ->whereIn('checkin_detail_id', $occupyingIds)
            ->whereIn('shift_log_id', $logIds)
            ->get();

        // POS cash transactions (no room charge) — these have null checkin_detail_id
        $cashPosTxns = Transaction::query()
            ->where('branch_id', $branchId)
            ->where('transaction_type_id', 9)
            ->whereNull('checkin_detail_id')
            ->whereIn('shift_log_id', $logIds)
            ->get();

        // ===== FRONTDESK CHART (build first so summary can use its subtotals) =====
        $frontdeskChart = $this->buildFrontdeskChart($floors, $allRooms, $occupyingDetails, $transactions, $timeIn, $timeOut, $menuParentMap);

        // ===== SUMMARY TABLE =====
        $summaryRows = $this->buildSummaryRows($floors, $transactions, $occupyingDetails, $timeIn, $timeOut, $frontdeskChart, $menuParentMap, $cashPosTxns);

        // ===== STATISTICS =====
        $totalNewGuest = NewGuestReport::where('branch_id', $branchId)
            ->whereBetween('created_at', [$timeIn, $timeOut])
            ->count();

        $totalExtendedGuest = ExtendedGuestReport::where('branch_id', $branchId)
            ->whereBetween('created_at', [$timeIn, $timeOut])
            ->count();

        $unoccupiedRooms = $allRooms->whereNotIn('id', $occupiedRoomIds);
        $totalUnoccupiedRooms = $unoccupiedRooms->count();
        $unoccupiedRoomNumbers = $unoccupiedRooms->sortBy('number')->pluck('number')->implode(', ');

        // ===== CLEANING ORDER =====
        $cleaningHistories = CleaningHistory::where('branch_id', $branchId)
            ->whereBetween('end_time', [$timeIn, $timeOut])
            ->with(['room', 'user'])
            ->orderBy('end_time')
            ->get();

        $cleaningOrder = $cleaningHistories->pluck('room.number')->unique()->implode(', ');

        // ===== ROOMS UNDER REPAIR =====
        $maintenanceRooms = $allRooms->where('status', 'Maintenance')
            ->sortBy('number')->pluck('number')->implode(', ');

        // ===== EXPENSES =====
        $expenses = Expense::whereIn('shift_log_id', $logIds)
            ->with('expenseCategory')
            ->get();
        $expensesTotal = (float) $expenses->sum('amount');

        // ===== PAYMENT ON SHORT (ADDITIONALS) =====
        $paymentOnShorts = PaymentOnShort::where('branch_id', $branchId)
            ->whereIn('shift_log_id', $logIds)
            ->with(['user', 'shiftLog'])
            ->get();
        $paymentOnShortsTotal = (float) $paymentOnShorts->sum('amount');

        // ===== ROOM CLEANING CHART =====
        $roomCleaningChart = $this->buildRoomCleaningChart($floors, $allRooms, $cleaningHistories, $occupiedRoomIds, $occupyingDetails, $timeIn, $timeOut);

        // ===== ROOM BOY ACTIVITY LOGS =====
        $roomboyLogs = $this->buildRoomboyLogs($cleaningHistories, $occupyingDetails, $timeIn, $timeOut);

        // ===== STOCKS INVENTORY =====
        $stocksData = $this->stocksInventoryRows($timeIn, $timeOut, $branchId, $logIds);

        return [
            'floors' => $floors,
            'summaryRows' => $summaryRows,
            'totalNewGuest' => $totalNewGuest,
            'totalExtendedGuest' => $totalExtendedGuest,
            'totalUnoccupiedRooms' => $totalUnoccupiedRooms,
            'unoccupiedRoomNumbers' => $unoccupiedRoomNumbers,
            'cleaningOrder' => $cleaningOrder,
            'maintenanceRooms' => $maintenanceRooms,
            'expenses' => $expenses,
            'expensesTotal' => $expensesTotal,
            'paymentOnShorts' => $paymentOnShorts,
            'paymentOnShortsTotal' => $paymentOnShortsTotal,
            'frontdeskChart' => $frontdeskChart,
            'roomCleaningChart' => $roomCleaningChart,
            'roomboyLogs' => $roomboyLogs,
            'stocksInventory' => $stocksData['groups'],
            'stocksSummary' => $stocksData['summary'],
        ];
    }

    private function buildSummaryRows($floors, $transactions, $occupyingDetails = null, Carbon $timeIn = null, Carbon $timeOut = null, array $frontdeskChart = [], array $menuParentMap = [], $cashPosTxns = null): array
    {
        $categories = [
            'ROOM' => [1],            // Check In
            'EXTEND' => [6],          // Extend
            'FOODS' => [9],           // Food and Beverages (filtered by parent category)
            'DRINKS' => [9],          // Food and Beverages (filtered by parent category)
            'MISCELLANEOUS' => [4, 8], // Damages + Amenities
        ];

        $rows = [];
        $grossPerFloor = [];
        foreach ($floors as $floor) {
            $grossPerFloor[$floor->id] = 0;
        }
        $grossTotal = 0;

        foreach ($categories as $label => $typeIds) {
            $row = ['label' => $label, 'floors' => [], 'total' => 0];
            foreach ($floors as $floor) {
                $floorTxns = $transactions
                    ->whereIn('transaction_type_id', $typeIds)
                    ->where('floor_id', $floor->id);

                // Split type 9 by parent category
                if ($label === 'FOODS') {
                    $floorTxns = $floorTxns->filter(fn ($t) => ($menuParentMap[$t->menu_id] ?? '') !== 'DRINKS');
                } elseif ($label === 'DRINKS') {
                    $floorTxns = $floorTxns->filter(fn ($t) => ($menuParentMap[$t->menu_id] ?? '') === 'DRINKS');
                }

                $amount = (float) $floorTxns->sum('payable_amount');
                $row['floors'][$floor->id] = $amount;
                $row['total'] += $amount;
                $grossPerFloor[$floor->id] += $amount;
            }
            // NO ROOM column (room-charged transactions without a floor match + POS cash transactions)
            $noRoomTxns = $transactions
                ->whereIn('transaction_type_id', $typeIds)
                ->whereNotIn('floor_id', $floors->pluck('id')->toArray());

            if ($label === 'FOODS') {
                $noRoomTxns = $noRoomTxns->filter(fn ($t) => ($menuParentMap[$t->menu_id] ?? '') !== 'DRINKS');
                // Add POS cash food transactions
                $cashFoods = $cashPosTxns ? $cashPosTxns->filter(fn ($t) => ($menuParentMap[$t->menu_id] ?? '') !== 'DRINKS') : collect();
                $noRoom = (float) $noRoomTxns->sum('payable_amount') + (float) $cashFoods->sum('payable_amount');
            } elseif ($label === 'DRINKS') {
                $noRoomTxns = $noRoomTxns->filter(fn ($t) => ($menuParentMap[$t->menu_id] ?? '') === 'DRINKS');
                // Add POS cash drink transactions
                $cashDrinks = $cashPosTxns ? $cashPosTxns->filter(fn ($t) => ($menuParentMap[$t->menu_id] ?? '') === 'DRINKS') : collect();
                $noRoom = (float) $noRoomTxns->sum('payable_amount') + (float) $cashDrinks->sum('payable_amount');
            } else {
                $noRoom = (float) $noRoomTxns->sum('payable_amount');
            }
            $row['no_room'] = $noRoom;
            $row['total'] += $noRoom;
            $grossTotal += $row['total'];
            $rows[] = $row;
        }

        // Gross Total row
        $grossRow = ['label' => 'GROSS TOTAL', 'floors' => $grossPerFloor, 'no_room' => 0, 'total' => $grossTotal];
        $noRoomGross = collect($rows)->sum('no_room');
        $grossRow['no_room'] = $noRoomGross;
        $rows[] = $grossRow;

        // TOTAL DEPOSIT row: derived from frontdesk chart subtotals so they always match
        // Combines room_deposit + guest deposit per floor
        $depositRow = ['label' => 'TOTAL DEPOSIT', 'floors' => [], 'total' => 0, 'no_room' => 0];
        foreach ($floors as $floor) {
            $floorDeposit = 0;
            foreach ($frontdeskChart as $floorGroup) {
                if ($floorGroup['floor']->id === $floor->id && !empty($floorGroup['subtotal'])) {
                    $floorDeposit = ($floorGroup['subtotal']['room_deposit'] ?? 0) + ($floorGroup['subtotal']['deposit'] ?? 0);
                    break;
                }
            }
            $depositRow['floors'][$floor->id] = $floorDeposit;
            $depositRow['total'] += $floorDeposit;
        }
        $rows[] = $depositRow;

        return $rows;
    }

    private function roomTypeInitial(string $name): string
    {
        $first = strtolower(trim(explode(' ', trim($name))[0]));
        return match ($first) {
            'single' => 'S',
            'double' => 'D',
            'twin' => 'T',
            default => $name,
        };
    }

    private function buildFrontdeskChart($floors, $allRooms, $occupyingDetails, $transactions, Carbon $timeIn, Carbon $timeOut, array $menuParentMap = []): array
    {
        // Pre-fetch ALL deposit (type 2) and cashout (type 5) transactions for forwarded guests
        // since their deposits were created in a previous shift and aren't in $transactions
        $forwardedIds = $occupyingDetails
            ->filter(fn($d) => Carbon::parse($d->check_in_at)->lt($timeIn))
            ->pluck('id')
            ->toArray();

        $forwardedDepositTxns = empty($forwardedIds) ? collect() : Transaction::query()
            ->whereIn('checkin_detail_id', $forwardedIds)
            ->whereIn('transaction_type_id', [2, 5])
            ->get();

        // Pre-fetch 6-hour base rates for all room types (keyed by type_id)
        $branchId = auth()->user()->branch_id;
        $baseRatesByType = \App\Models\Rate::where('branch_id', $branchId)
            ->whereHas('stayingHour', fn($q) => $q->where('number', 6))
            ->pluck('amount', 'type_id')
            ->toArray();

        $chart = [];

        foreach ($floors as $floor) {
            $floorRooms = $allRooms->where('floor_id', $floor->id)->sortBy('number');
            $floorData = [];

            foreach ($floorRooms as $room) {
                $roomCheckins = $occupyingDetails->where('room_id', $room->id)->sortBy('created_at');

                if ($roomCheckins->isEmpty()) {
                    // Available room — single empty row
                    $floorData[] = [
                        'number' => $room->number,
                        'type' => $this->roomTypeInitial($room->type->name ?? ''),
                        'rate' => $baseRatesByType[$room->type_id] ?? '',
                        'status' => 'Available',
                        'guest' => '',
                        'check_in' => '',
                        'check_out' => '',
                        'initial_hours' => '',
                        'is_forwarded' => false,
                        'has_extend' => false,
                        'rowspan' => 1,
                        'room_rate' => 0,
                        'extend' => 0,
                        'foods' => 0,
                        'drinks' => 0,
                        'misc' => 0,
                        'room_deposit' => 0,
                        'deposit' => 0,
                        'expected_check_out' => '',
                    ];
                } else {
                    $checkinCount = $roomCheckins->count();
                    $isFirst = true;

                    foreach ($roomCheckins as $checkin) {
                        $checkinTxns = $transactions->where('checkin_detail_id', $checkin->id);

                        $isForwarded = Carbon::parse($checkin->check_in_at)->lt($timeIn);
                        $status = $isForwarded ? 'FWD' : 'Occupied';

                        $floorData[] = [
                            'number' => $room->number,
                            'type' => $this->roomTypeInitial($room->type->name ?? ''),
                            'rate' => $baseRatesByType[$room->type_id] ?? '',
                            'status' => $status,
                            'guest' => $checkin->guest?->name ?? '',
                            'check_in' => $checkin->check_in_at ? Carbon::parse($checkin->check_in_at)->format('m/d g:iA') : '',
                            'check_out' => $checkin->is_check_out && $checkin->check_out_at && Carbon::parse($checkin->check_out_at)->between($timeIn, $timeOut) ? Carbon::parse($checkin->check_out_at)->format('m/d g:iA') : '',
                            'initial_hours' => $checkin->rate?->stayingHour?->number ?? '',
                            'is_forwarded' => $isForwarded,
                            'rowspan' => $isFirst ? $checkinCount : 0,
                            'room_rate' => (float) $checkinTxns->where('transaction_type_id', 1)->sum('payable_amount')
                                        + (float) $checkinTxns->where('transaction_type_id', 6)->sum('payable_amount'),
                            'has_extend' => (float) $checkinTxns->where('transaction_type_id', 6)->sum('payable_amount') > 0,
                            'extend' => (float) $checkinTxns->where('transaction_type_id', 6)->sum('payable_amount'),
                            'foods' => (float) $checkinTxns->where('transaction_type_id', 9)->filter(fn ($t) => ($menuParentMap[$t->menu_id] ?? '') !== 'DRINKS')->sum('payable_amount'),
                            'drinks' => (float) $checkinTxns->where('transaction_type_id', 9)->filter(fn ($t) => ($menuParentMap[$t->menu_id] ?? '') === 'DRINKS')->sum('payable_amount'),
                            'misc' => (float) $checkinTxns->whereIn('transaction_type_id', [4, 8])->sum('payable_amount'),
                            'room_deposit' => ($checkin->is_check_out && $checkin->check_out_at && Carbon::parse($checkin->check_out_at)->between($timeIn, $timeOut))
                                ? 0
                                : (float) ($isForwarded ? $forwardedDepositTxns : $checkinTxns)->where('checkin_detail_id', $checkin->id)->where('transaction_type_id', 2)->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')->sum('payable_amount'),
                            'deposit' => ($checkin->is_check_out && $checkin->check_out_at && Carbon::parse($checkin->check_out_at)->between($timeIn, $timeOut))
                                ? 0
                                : max(0,
                                    (float) ($isForwarded ? $forwardedDepositTxns : $checkinTxns)->where('checkin_detail_id', $checkin->id)->where('transaction_type_id', 2)->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')->when($isForwarded, fn($c) => $c->filter(fn($t) => $t->created_at <= $timeOut))->sum('payable_amount')
                                    - (float) ($isForwarded ? $forwardedDepositTxns : $checkinTxns)->where('checkin_detail_id', $checkin->id)->where('transaction_type_id', 5)->when($isForwarded, fn($c) => $c->filter(fn($t) => $t->created_at <= $timeOut))->sum('payable_amount')
                                ),
                            'expected_check_out' => $checkin->check_out_at
                                ? Carbon::parse($checkin->check_out_at)->format('m/d g:iA')
                                : '',
                        ];

                        $isFirst = false;
                    }
                }
            }

            // Floor subtotals
            $subtotal = [
                'room_rate' => collect($floorData)->sum('room_rate'),
                'extend' => collect($floorData)->sum('extend'),
                'foods' => collect($floorData)->sum('foods'),
                'drinks' => collect($floorData)->sum('drinks'),
                'misc' => collect($floorData)->sum('misc'),
                'room_deposit' => collect($floorData)->sum('room_deposit'),
                'deposit' => collect($floorData)->sum('deposit'),
            ];

            $chart[] = [
                'floor' => $floor,
                'rooms' => $floorData,
                'subtotal' => $subtotal,
            ];
        }

        return $chart;
    }

    private function buildRoomCleaningChart($floors, $allRooms, $cleaningHistories, array $occupiedRoomIds, $occupyingDetails, Carbon $timeIn, Carbon $timeOut): array
    {
        // Group cleaning histories by room_id (keep all, sorted by parsed end_time timestamp)
        $cleaningByRoom = $cleaningHistories->groupBy('room_id')->map(fn($group) => $group->sortBy(fn($c) => Carbon::parse($c->end_time)->timestamp)->values());

        // For orphan cleanings, get the most recent checkout from before the shift
        $roomIdsWithCleanings = $cleaningByRoom->keys()->toArray();
        $priorCheckoutsByRoom = empty($roomIdsWithCleanings) ? collect() : CheckinDetail::query()
            ->whereIn('room_id', $roomIdsWithCleanings)
            ->where('is_check_out', 1)
            ->whereNotNull('check_out_at')
            ->where('check_out_at', '<', $timeIn)
            ->where('check_out_at', '>=', $timeIn->copy()->subDays(7))
            ->orderBy('check_out_at', 'desc')
            ->get()
            ->groupBy('room_id');

        $chart = [];
        foreach ($floors as $floor) {
            $rooms = $allRooms->where('floor_id', $floor->id)->sortBy('number')->values();
            $roomData = [];

            foreach ($rooms as $room) {
                $roomCheckins = $occupyingDetails->where('room_id', $room->id)->sortBy('check_in_at');
                $roomCleanings = $cleaningByRoom->get($room->id, collect())->values();
                $usedCleaningIds = [];
                $roomRows = [];

                foreach ($roomCheckins as $checkin) {
                    $time = '';
                    $isCheckedOut = $checkin->is_check_out && $checkin->check_out_at && Carbon::parse($checkin->check_out_at)->between($timeIn, $timeOut);
                    $status = $isCheckedOut ? 'Clean' : 'In-use';

                    $durationSeconds = 0;
                    $duration = '';
                    if ($isCheckedOut) {
                        $checkOutAt = Carbon::parse($checkin->check_out_at);
                        $matchedCleaning = $roomCleanings
                            ->reject(fn($c) => in_array($c->id, $usedCleaningIds))
                            ->filter(fn($c) => $c->end_time && Carbon::parse($c->end_time)->gte($checkOutAt))
                            ->sortBy(fn($c) => Carbon::parse($c->end_time)->timestamp)
                            ->first();

                        if ($matchedCleaning) {
                            $usedCleaningIds[] = $matchedCleaning->id;
                            $endTime = Carbon::parse($matchedCleaning->end_time);
                            $time = $endTime->format('g:iA');
                            // Elapse = checkout → cleaning end (total wait time)
                            $durationSeconds = $checkOutAt->diffInSeconds($endTime);
                            $duration = $this->formatDuration($durationSeconds);
                        }
                    } else {
                        // Forwarded guest — show cleaning that finished before they arrived, if any
                        $checkInAt = Carbon::parse($checkin->check_in_at);
                        $delayedCleaning = $roomCleanings
                            ->reject(fn($c) => in_array($c->id, $usedCleaningIds))
                            ->filter(fn($c) => $c->end_time && Carbon::parse($c->end_time)->lte($checkInAt))
                            ->sortBy(fn($c) => Carbon::parse($c->end_time)->timestamp)
                            ->last();

                        if ($delayedCleaning) {
                            $usedCleaningIds[] = $delayedCleaning->id;
                            $status = 'Clean';
                            $endTime = Carbon::parse($delayedCleaning->end_time);
                            $time = $endTime->format('g:iA');
                            // Elapse = checkout → cleaning end (find prior checkout).
                            // Skip the elapse when another guest checked in between
                            // the prior checkout and this cleaning — a later stay
                            // intervened, so that prior checkout is not the true
                            // start of the cleaning wait.
                            $priorCheckout = ($priorCheckoutsByRoom[$room->id] ?? collect())
                                ->first(fn($d) => Carbon::parse($d->check_out_at)->lte($endTime));
                            if ($priorCheckout) {
                                $priorCheckOutAt = Carbon::parse($priorCheckout->check_out_at);
                                $hasInterveningCheckin = $roomCheckins->contains(function ($c) use ($priorCheckOutAt, $endTime) {
                                    if (!$c->check_in_at) {
                                        return false;
                                    }
                                    $ci = Carbon::parse($c->check_in_at);
                                    return $ci->gt($priorCheckOutAt) && $ci->lt($endTime);
                                });
                                if (!$hasInterveningCheckin) {
                                    $durationSeconds = $priorCheckOutAt->diffInSeconds($endTime);
                                    $duration = $this->formatDuration($durationSeconds);
                                }
                            }
                        }
                    }

                    $roomRows[] = [
                        'number' => $room->number,
                        'time' => $time,
                        'duration' => $duration,
                        'duration_seconds' => $durationSeconds,
                        'status' => $status,
                    ];
                }

                // Orphan cleanings: cleaning ended in this shift but its checkout was outside the shift window.
                $orphanCleanings = $roomCleanings->reject(fn($c) => in_array($c->id, $usedCleaningIds));
                foreach ($orphanCleanings as $orphan) {
                    $orphanEnd = Carbon::parse($orphan->end_time);
                    $usedCleaningIds[] = $orphan->id;

                    // Elapse = checkout → cleaning end. Skip the elapse when another
                    // guest checked in between the prior checkout and this cleaning —
                    // the room was re-occupied, so the prior checkout is not the true
                    // start of the cleaning wait (and 30h "red" elapses would mislead).
                    $orphanDurationSeconds = 0;
                    $orphanDuration = '';
                    $priorCheckout = ($priorCheckoutsByRoom[$room->id] ?? collect())
                        ->first(fn($d) => Carbon::parse($d->check_out_at)->lte($orphanEnd));

                    if ($priorCheckout) {
                        $priorCheckOutAt = Carbon::parse($priorCheckout->check_out_at);
                        $hasInterveningCheckin = $roomCheckins->contains(function ($c) use ($priorCheckOutAt, $orphanEnd) {
                            if (!$c->check_in_at) {
                                return false;
                            }
                            $ci = Carbon::parse($c->check_in_at);
                            return $ci->gt($priorCheckOutAt) && $ci->lt($orphanEnd);
                        });
                        if (!$hasInterveningCheckin) {
                            $orphanDurationSeconds = $priorCheckOutAt->diffInSeconds($orphanEnd);
                            $orphanDuration = $this->formatDuration($orphanDurationSeconds);
                        }
                    }

                    $roomRows[] = [
                        'number' => $room->number,
                        'time' => $orphanEnd->format('g:iA'),
                        'duration' => $orphanDuration,
                        'duration_seconds' => $orphanDurationSeconds,
                        'status' => 'Clean',
                    ];
                }

                // Room with no checkins and no cleanings — show a single Vacant row
                if (empty($roomRows)) {
                    $roomRows[] = [
                        'number' => $room->number,
                        'time' => '',
                        'duration' => '',
                        'duration_seconds' => 0,
                        'status' => 'Vacant',
                    ];
                }

                $totalRows = count($roomRows);
                foreach ($roomRows as $i => &$row) {
                    $row['rowspan'] = $i === 0 ? $totalRows : 0;
                }
                unset($row);

                $roomData = array_merge($roomData, $roomRows);
            }

            $chart[] = [
                'floor' => $floor,
                'rooms' => $roomData,
            ];
        }

        return $chart;
    }

    private function formatDuration(int $diffSeconds): string
    {
        $hours = intdiv($diffSeconds, 3600);
        $minutes = intdiv($diffSeconds % 3600, 60);
        $seconds = $diffSeconds % 60;

        return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
    }

    private function buildRoomboyLogs($cleaningHistories, $occupyingDetails, Carbon $timeIn, Carbon $timeOut): array
    {
        // Group cleaning histories by room_id (sorted by end_time)
        $cleaningByRoom = $cleaningHistories->groupBy('room_id')->map(fn($group) => $group->sortBy('end_time')->values());

        // Build a map of room_id => room boy user_id (from any cleaning record for that room)
        $roomToRoomboy = [];
        foreach ($cleaningHistories as $ch) {
            if (!isset($roomToRoomboy[$ch->room_id])) {
                $roomToRoomboy[$ch->room_id] = [
                    'user_id' => $ch->user_id,
                    'user_name' => $ch->user?->name ?? 'Unknown',
                ];
            }
        }

        // Group by room boy
        $roomboyEntries = [];
        $usedCleaningIds = [];

        // Iterate all occupying details (same data source as frontdesk chart), sorted by room then check_in
        $sortedDetails = $occupyingDetails->sortBy(['room_id', 'check_in_at']);

        foreach ($sortedDetails as $detail) {
            $roomId = $detail->room_id;
            $roomCleanings = $cleaningByRoom->get($roomId, collect());
            $isCheckedOut = $detail->is_check_out && $detail->check_out_at && Carbon::parse($detail->check_out_at)->between($timeIn, $timeOut);

            // Skip guests who haven't actually checked out during this shift
            if (!$isCheckedOut) {
                continue;
            }

            // Match cleaning record closest after this guest's checkout.
            // Do NOT fall back to a cleaning that ended before this guest checked in —
            // that would misattribute a prior guest's cleaning to the current guest.
            // Unclaimed cleanings are handled by the orphan loop below.
            $checkOutAt = Carbon::parse($detail->check_out_at);
            $matchedCleaning = $roomCleanings
                ->reject(fn($c) => in_array($c->id, $usedCleaningIds))
                ->filter(fn($c) => $c->end_time && Carbon::parse($c->end_time)->gte($checkOutAt))
                ->first();

            if ($matchedCleaning) {
                $usedCleaningIds[] = $matchedCleaning->id;
            }

            // Determine room boy: from matched cleaning, or skip
            if ($matchedCleaning) {
                $userId = $matchedCleaning->user_id;
                $roomboyName = $matchedCleaning->user?->name ?? 'Unknown';
            } else {
                // No cleaning record for this checkout — skip
                continue;
            }

            $elapse = '';
            if ($matchedCleaning && $matchedCleaning->start_time && $matchedCleaning->end_time) {
                $start = Carbon::parse($matchedCleaning->start_time);
                $end = Carbon::parse($matchedCleaning->end_time);
                $diffSeconds = $start->diffInSeconds($end);
                if ($diffSeconds < 60) {
                    $elapse = "{$diffSeconds} seconds (OVERRIDE)";
                } else {
                    $diffMinutes = intdiv($diffSeconds, 60);
                    $hours = intdiv($diffMinutes, 60);
                    $minutes = $diffMinutes % 60;
                    $elapse = $hours > 0 ? "{$hours} hour" . ($hours > 1 ? 's' : '') . " {$minutes} minutes" : "{$minutes} minutes";
                }
            }

            $roomboyEntries[$userId][] = [
                'roomboy_name' => strtoupper($roomboyName),
                'room_number' => $detail->room?->number ?? '',
                'room_id' => $roomId,
                'floor_number' => $detail->room?->floor?->number ?? '',
                'time' => $matchedCleaning && $matchedCleaning->end_time
                    ? Carbon::parse($matchedCleaning->end_time)->format('F d, Y g:iA')
                    : '',
                'elapse' => $elapse,
            ];
        }

        // Add orphaned delayed cleanings (room cleaned from prior shift's checkout, no matching guest in this shift)
        foreach ($cleaningByRoom as $roomId => $roomCleanings) {
            foreach ($roomCleanings as $cleaning) {
                if (in_array($cleaning->id, $usedCleaningIds)) {
                    continue;
                }

                $userId = $cleaning->user_id;
                $roomboyName = $cleaning->user?->name ?? 'Unknown';

                $elapse = '';
                if ($cleaning->start_time && $cleaning->end_time) {
                    $start = Carbon::parse($cleaning->start_time);
                    $end = Carbon::parse($cleaning->end_time);
                    $diffSeconds = $start->diffInSeconds($end);
                    if ($diffSeconds < 60) {
                        $elapse = "{$diffSeconds} seconds (OVERRIDE)";
                    } else {
                        $diffMinutes = intdiv($diffSeconds, 60);
                        $hours = intdiv($diffMinutes, 60);
                        $minutes = $diffMinutes % 60;
                        $elapse = $hours > 0 ? "{$hours} hour" . ($hours > 1 ? 's' : '') . " {$minutes} minutes" : "{$minutes} minutes";
                    }
                }

                $roomboyEntries[$userId][] = [
                    'roomboy_name' => strtoupper($roomboyName),
                    'room_number' => $cleaning->room?->number ?? '',
                    'room_id' => $roomId,
                    'floor_number' => $cleaning->room?->floor?->number ?? '',
                    'time' => $cleaning->end_time
                        ? Carbon::parse($cleaning->end_time)->format('F d, Y g:iA')
                        : '',
                    'elapse' => $elapse,
                ];
            }
        }

        // Build final logs grouped by room boy, with rowspan calculated per room within each group
        $logs = [];
        foreach ($roomboyEntries as $userId => $entries) {
            // Calculate rowspan per room within this room boy's entries
            $roomCounts = collect($entries)->groupBy('room_id')->map->count();
            $roomSeenCount = [];

            $numberedEntries = [];
            foreach (array_values($entries) as $i => $entry) {
                $rid = $entry['room_id'];
                $roomSeenCount[$rid] = ($roomSeenCount[$rid] ?? 0) + 1;
                $isFirstForRoom = $roomSeenCount[$rid] === 1;
                $totalForRoom = $roomCounts[$rid] ?? 1;

                $numberedEntries[] = [
                    'number' => $i + 1,
                    'room_number' => $entry['room_number'],
                    'floor_number' => $entry['floor_number'],
                    'rowspan' => $isFirstForRoom ? $totalForRoom : 0,
                    'time' => $entry['time'],
                    'elapse' => $entry['elapse'],
                ];
            }
            $logs[] = [
                'name' => $entries[0]['roomboy_name'],
                'entries' => $numberedEntries,
            ];
        }

        return $logs;
    }

    private function stocksInventoryRows(Carbon $timeIn, Carbon $timeOut, int $branchId, array $sessionLogIds = []): array
    {
        // Load ALL frontdesk inventory items (not just those with movements)
        $allInventory = \App\Models\FrontdeskInventory::where('branch_id', $branchId)->get();

        if ($allInventory->isEmpty()) {
            return ['groups' => [], 'summary' => []];
        }

        $menuIds = $allInventory->pluck('frontdesk_menu_id')->unique()->filter()->values();
        $menus = FrontdeskMenu::whereIn('id', $menuIds)
            ->with(['frontdeskCategory.parentCategory'])
            ->get()
            ->keyBy('id');

        // Snapshot-based sales totals: sum payable_amount frozen at checkout time,
        // so historical reports are not retroactively re-priced when menu prices change.
        $salesByMenuId = Transaction::query()
            ->where('branch_id', $branchId)
            ->where('transaction_type_id', 9)
            ->whereIn('menu_id', $menuIds)
            ->when(!empty($sessionLogIds),
                fn($q) => $q->whereIn('shift_log_id', $sessionLogIds),
                fn($q) => $q->whereBetween('created_at', [$timeIn, $timeOut])
            )
            ->whereNull('voided_at')
            ->selectRaw('menu_id, SUM(payable_amount) as total')
            ->groupBy('menu_id')
            ->pluck('total', 'menu_id');

        $items = [];

        foreach ($allInventory as $inv) {
            $menu = $menus[$inv->frontdesk_menu_id] ?? null;
            if (!$menu) continue;

            $category       = $menu->frontdeskCategory;
            $parentCategory = $category?->parentCategory;
            $parentName     = $parentCategory?->name ?? 'UNCATEGORIZED';
            $categoryName   = $category?->name ?? 'UNCATEGORIZED';

            // Kitchen (warehouse) opening balance
            $prevKitchen = StockMovement::where('source_type', StockMovement::SOURCE_KITCHEN)
                ->where('inventory_id', $inv->id)
                ->where('created_at', '<', $timeIn)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->first();
            $kitchenOpening = $prevKitchen ? (float) $prevKitchen->balance_after : 0.0;

            // Kitchen movements during shift
            $kitchenMovements = StockMovement::where('source_type', StockMovement::SOURCE_KITCHEN)
                ->where('inventory_id', $inv->id)
                ->whereBetween('created_at', [$timeIn, $timeOut])
                ->orderBy('id')
                ->get();

            $kitchenIn  = (float) $kitchenMovements->whereIn('type', [StockMovement::TYPE_IN, StockMovement::TYPE_VOID])->sum('quantity');
            $kitchenOut = (float) $kitchenMovements->where('type', StockMovement::TYPE_OUT)->sum('quantity');
            $kitchenClosing = $kitchenMovements->isNotEmpty() ? (float) $kitchenMovements->last()->balance_after : $kitchenOpening;

            // Frontdesk opening balance
            $prevFrontdesk = StockMovement::where('source_type', StockMovement::SOURCE_FRONTDESK)
                ->where('inventory_id', $inv->id)
                ->where('created_at', '<', $timeIn)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->first();
            $frontdeskOpening = $prevFrontdesk ? (float) $prevFrontdesk->balance_after : 0.0;

            // Frontdesk movements during shift
            $frontdeskMovements = StockMovement::where('source_type', StockMovement::SOURCE_FRONTDESK)
                ->where('inventory_id', $inv->id)
                ->whereBetween('created_at', [$timeIn, $timeOut])
                ->orderBy('id')
                ->get();

            $frontdeskIn  = (float) $frontdeskMovements->whereIn('type', [StockMovement::TYPE_IN, StockMovement::TYPE_VOID])->sum('quantity');
            $frontdeskOut = (float) $frontdeskMovements->where('type', StockMovement::TYPE_OUT)->sum('quantity');
            $frontdeskClosing = $frontdeskMovements->isNotEmpty() ? (float) $frontdeskMovements->last()->balance_after : $frontdeskOpening;

            $price      = (float) $menu->price;
            $stockSold  = $frontdeskOut;
            $salesTotal = (float) ($salesByMenuId[$menu->id] ?? 0);

            $items[] = [
                'parent_category' => strtoupper($parentName),
                'category'        => strtoupper($categoryName),
                'name'            => $menu->name,
                'price'           => $price,
                'kitchen'         => ['opening' => $kitchenOpening, 'in' => $kitchenIn, 'out' => $kitchenOut, 'closing' => $kitchenClosing],
                'frontdesk'       => ['opening' => $frontdeskOpening, 'in' => $frontdeskIn, 'out' => $frontdeskOut, 'closing' => $frontdeskClosing],
                'total_remaining' => $kitchenClosing + $frontdeskClosing,
                'combo_ded'       => 0,
                'stock_sold'      => $stockSold,
                'sales_total'     => $salesTotal,
            ];
        }

        $groups = [];
        foreach ($items as $item) {
            $pc  = $item['parent_category'];
            $cat = $item['category'];
            if (!isset($groups[$pc])) {
                $groups[$pc] = ['categories' => [], 'total_sales' => 0];
            }
            if (!isset($groups[$pc]['categories'][$cat])) {
                $groups[$pc]['categories'][$cat] = ['items' => []];
            }
            $groups[$pc]['categories'][$cat]['items'][] = $item;
            $groups[$pc]['total_sales'] += $item['sales_total'];
        }

        foreach ($groups as &$group) {
            ksort($group['categories']);
            foreach ($group['categories'] as &$cat) {
                usort($cat['items'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
            }
        }
        unset($group, $cat);

        $summary = [];
        $grandTotal = 0;
        foreach ($groups as $parentName => $group) {
            $summary[] = ['label' => "TOTAL {$parentName} SALES", 'amount' => $group['total_sales']];
            $grandTotal += $group['total_sales'];
        }
        $summary[] = ['label' => 'TOTAL SALES', 'amount' => $grandTotal];

        return ['groups' => $groups, 'summary' => $summary];
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
            $shiftType = $this->shiftTypeOf($log);
            $shiftDate = ShiftResolver::deriveShiftDate($log->time_in, $shiftType)->format('Y-m-d');
            $key = $shiftType . '_' . $shiftDate;

            if (!isset($sessions[$key])) {
                $sessions[$key] = [
                    'id' => $log->id,
                    'log_ids' => [],
                    'time_in' => $log->time_in,
                    'time_out' => $log->time_out,
                    'shift_type' => $shiftType,
                    'shift_date' => $shiftDate,
                    'frontdesks' => [],
                ];
            }

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
                    'shift_type' => $s['shift_type'],
                    'shift_date' => $s['shift_date'],
                    'time_in' => $s['time_in']->toIso8601String(),
                    'time_out' => $s['time_out']->toIso8601String(),
                    'time_in_formatted' => $s['time_in']->format('F d, Y g:i A'),
                    'time_out_formatted' => $s['time_out']->format('F d, Y g:i A'),
                    'date_formatted' => $s['time_in']->format('l, F d, Y'),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getShiftType(Carbon $timeIn): string
    {
        return ShiftResolver::fromClock($timeIn);
    }

    private function shiftTypeOf(ShiftLog $log): string
    {
        return $log->shift ?? ShiftResolver::fromClock($log->time_in);
    }

    private function getSelectedSession(): ?array
    {
        return collect($this->availableShiftSessions)
            ->firstWhere('id', $this->selectedShiftLogId);
    }
}
