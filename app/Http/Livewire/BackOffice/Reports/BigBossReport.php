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
use Carbon\Carbon;

class BigBossReport extends Component
{
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
            'frontdeskChart' => [],
            'roomCleaningChart' => [],
            'roomboyLogs' => [],
        ];

        if (!$session) {
            return $empty;
        }

        $branchId = auth()->user()->branch_id;
        $timeIn = Carbon::parse($session['time_in']);
        $timeOut = Carbon::parse($session['time_out']);

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

        $occupyingIds = $occupyingDetails->pluck('id')->toArray();
        $occupiedRoomIds = $occupyingDetails->pluck('room_id')->unique()->toArray();

        // All transactions for occupying guests in this shift
        $transactions = empty($occupyingIds) ? collect() : Transaction::query()
            ->whereIn('checkin_detail_id', $occupyingIds)
            ->whereBetween('created_at', [$timeIn, $timeOut])
            ->get();

        // ===== SUMMARY TABLE =====
        $summaryRows = $this->buildSummaryRows($floors, $transactions);

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
            ->whereBetween('created_at', [$timeIn, $timeOut])
            ->with(['room', 'user'])
            ->orderBy('created_at')
            ->get();

        $cleaningOrder = $cleaningHistories->pluck('room.number')->unique()->implode(', ');

        // ===== ROOMS UNDER REPAIR =====
        $maintenanceRooms = $allRooms->where('status', 'Maintenance')
            ->sortBy('number')->pluck('number')->implode(', ');

        // ===== EXPENSES =====
        $expenses = Expense::whereBetween('created_at', [$timeIn, $timeOut])
            ->with('expenseCategory')
            ->get();
        $expensesTotal = (float) $expenses->sum('amount');

        // ===== FRONTDESK CHART =====
        $frontdeskChart = $this->buildFrontdeskChart($floors, $allRooms, $occupyingDetails, $transactions, $timeIn, $timeOut);

        // ===== ROOM CLEANING CHART =====
        $roomCleaningChart = $this->buildRoomCleaningChart($floors, $allRooms, $cleaningHistories, $occupiedRoomIds, $occupyingDetails);

        // ===== ROOM BOY ACTIVITY LOGS =====
        $roomboyLogs = $this->buildRoomboyLogs($cleaningHistories);

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
            'frontdeskChart' => $frontdeskChart,
            'roomCleaningChart' => $roomCleaningChart,
            'roomboyLogs' => $roomboyLogs,
        ];
    }

    private function buildSummaryRows($floors, $transactions): array
    {
        $categories = [
            'ROOM' => [1],            // Check In
            'TRANSFER' => [7],        // Transfer Room
            'EXTEND' => [6],          // Extend
            'FOODS' => [9],           // Food and Beverages
            'DRINKS' => [],           // No separate type
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
                $amount = empty($typeIds) ? 0 : (float) $transactions
                    ->whereIn('transaction_type_id', $typeIds)
                    ->where('floor_id', $floor->id)
                    ->sum('payable_amount');
                $row['floors'][$floor->id] = $amount;
                $row['total'] += $amount;
                $grossPerFloor[$floor->id] += $amount;
            }
            // NO ROOM column (transactions without a floor match)
            $noRoom = empty($typeIds) ? 0 : (float) $transactions
                ->whereIn('transaction_type_id', $typeIds)
                ->whereNotIn('floor_id', $floors->pluck('id')->toArray())
                ->sum('payable_amount');
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

        // Deposit row (guest deposits only, exclude check-in deposits)
        $guestDeposits = $transactions
            ->where('transaction_type_id', 2)
            ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)');

        $depositRow = ['label' => 'TOTAL DEPOSIT', 'floors' => [], 'total' => 0, 'no_room' => 0];
        foreach ($floors as $floor) {
            $amount = (float) $guestDeposits
                ->where('floor_id', $floor->id)
                ->sum('payable_amount');
            $depositRow['floors'][$floor->id] = $amount;
            $depositRow['total'] += $amount;
        }
        $noRoomDeposit = (float) $guestDeposits
            ->whereNotIn('floor_id', $floors->pluck('id')->toArray())
            ->sum('payable_amount');
        $depositRow['no_room'] = $noRoomDeposit;
        $depositRow['total'] += $noRoomDeposit;
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

    private function buildFrontdeskChart($floors, $allRooms, $occupyingDetails, $transactions, Carbon $timeIn, Carbon $timeOut): array
    {
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
                        'rate' => '',
                        'status' => 'Available',
                        'guest' => '',
                        'check_in' => '',
                        'check_out' => '',
                        'initial_hours' => '',
                        'is_forwarded' => false,
                        'rowspan' => 1,
                        'room_rate' => 0,
                        'transfer' => 0,
                        'extend' => 0,
                        'foods' => 0,
                        'drinks' => 0,
                        'misc' => 0,
                        'room_deposit' => 0,
                        'deposit' => 0,
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
                            'rate' => $checkin->rate?->amount ?? '',
                            'status' => $status,
                            'guest' => $checkin->guest?->name ?? '',
                            'check_in' => $checkin->check_in_at ? Carbon::parse($checkin->check_in_at)->format('m/d g:iA') : '',
                            'check_out' => $checkin->check_out_at && Carbon::parse($checkin->check_out_at)->between($timeIn, $timeOut) ? Carbon::parse($checkin->check_out_at)->format('m/d g:iA') : '',
                            'initial_hours' => $checkin->rate?->stayingHour?->number ?? '',
                            'is_forwarded' => $isForwarded,
                            'rowspan' => $isFirst ? $checkinCount : 0,
                            'room_rate' => (float) $checkinTxns->where('transaction_type_id', 1)->sum('payable_amount'),
                            'transfer' => (float) $checkinTxns->where('transaction_type_id', 7)->sum('payable_amount'),
                            'extend' => (float) $checkinTxns->where('transaction_type_id', 6)->sum('payable_amount'),
                            'foods' => (float) $checkinTxns->where('transaction_type_id', 9)->sum('payable_amount'),
                            'drinks' => 0,
                            'misc' => (float) $checkinTxns->whereIn('transaction_type_id', [4, 8])->sum('payable_amount'),
                            'room_deposit' => (float) $checkinTxns->where('transaction_type_id', 2)->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')->sum('payable_amount'),
                            'deposit' => (float) $checkinTxns->where('transaction_type_id', 2)->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')->sum('payable_amount')
                                     - (float) $checkinTxns->where('transaction_type_id', 5)->sum('payable_amount'),
                        ];

                        $isFirst = false;
                    }
                }
            }

            $chart[] = [
                'floor' => $floor,
                'rooms' => $floorData,
            ];
        }

        return $chart;
    }

    private function buildRoomCleaningChart($floors, $allRooms, $cleaningHistories, array $occupiedRoomIds, $occupyingDetails): array
    {
        // Group cleaning histories by room_id, keep the latest per room
        $cleaningByRoom = $cleaningHistories->groupBy('room_id')->map(fn($group) => $group->last());

        $chart = [];
        foreach ($floors as $floor) {
            $rooms = $allRooms->where('floor_id', $floor->id)->sortBy('number')->values();
            $roomData = [];

            foreach ($rooms as $room) {
                $cleaning = $cleaningByRoom->get($room->id);
                $time = '';
                $elapse = '';
                $status = in_array($room->id, $occupiedRoomIds) ? 'In-use' : 'Vacant';

                if ($cleaning) {
                    $time = $cleaning->end_time ? Carbon::parse($cleaning->end_time)->format('g:iA') : '';

                    if ($cleaning->end_time) {
                        $endTime = Carbon::parse($cleaning->end_time);

                        // Find the checkin_detail for this room that was checked out closest before cleaning
                        $checkoutDetail = $occupyingDetails
                            ->where('room_id', $room->id)
                            ->filter(fn($cd) => $cd->check_out_at && Carbon::parse($cd->check_out_at)->lte($endTime))
                            ->sortByDesc('check_out_at')
                            ->first();

                        if ($checkoutDetail && $checkoutDetail->check_out_at) {
                            $checkoutTime = Carbon::parse($checkoutDetail->check_out_at);
                            $diffSeconds = $checkoutTime->diffInSeconds($endTime);
                            $hours = intdiv($diffSeconds, 3600);
                            $remainingMinutes = intdiv($diffSeconds % 3600, 60);
                            $remainingSeconds = $diffSeconds % 60;
                            if ($hours > 0) {
                                $elapse = $hours . ':' . str_pad($remainingMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($remainingSeconds, 2, '0', STR_PAD_LEFT);
                            } else {
                                $elapse = $remainingMinutes . ':' . str_pad($remainingSeconds, 2, '0', STR_PAD_LEFT);
                            }
                        }
                    }

                    $status = 'Clean';
                }

                $roomData[] = [
                    'number' => $room->number,
                    'time' => $time,
                    'elapse' => $elapse,
                    'status' => $status,
                ];
            }

            $chart[] = [
                'floor' => $floor,
                'rooms' => $roomData,
            ];
        }

        return $chart;
    }

    private function buildRoomboyLogs($cleaningHistories): array
    {
        $grouped = $cleaningHistories->groupBy('user_id');
        $logs = [];

        foreach ($grouped as $userId => $histories) {
            $roomboy = $histories->first()->user;
            $entries = [];
            foreach ($histories->values() as $i => $h) {
                $elapse = '';
                if ($h->start_time && $h->end_time) {
                    $start = Carbon::parse($h->start_time);
                    $end = Carbon::parse($h->end_time);
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

                $entries[] = [
                    'number' => $i + 1,
                    'room_number' => $h->room?->number ?? '',
                    'floor_number' => $h->room?->floor?->number ?? $h->floor_id,
                    'time' => $h->end_time ? Carbon::parse($h->end_time)->format('F d, Y g:iA') : '',
                    'elapse' => $elapse,
                ];
            }
            $logs[] = [
                'name' => strtoupper($roomboy?->name ?? 'Unknown'),
                'entries' => $entries,
            ];
        }

        return $logs;
    }

    private function loadAvailableShiftSessions(): void
    {
        $shiftLogs = ShiftLog::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->whereNotNull('time_out')
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
        $hour = $timeIn->hour;
        return ($hour >= 6 && $hour < 20) ? 'AM' : 'PM';
    }

    private function getSelectedSession(): ?array
    {
        return collect($this->availableShiftSessions)
            ->firstWhere('id', $this->selectedShiftLogId);
    }
}
