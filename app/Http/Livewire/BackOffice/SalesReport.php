<?php

namespace App\Http\Livewire\BackOffice;

use Livewire\Component;
use App\Models\Frontdesk;
use App\Models\CheckinDetail;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesReport extends Component
{
    public string $type = 'Overall Sales';

    public bool $showExtend = true;
    public bool $showAmenities = true;
    public bool $showFood = true;
    public bool $showDamages = true;
    public bool $showTransfer = true;
    public bool $showDeposits = true;

    public float $totalSales = 0;

    public $date_from;
    public $time_from;

    public $date_to;
    public $time_to;
    public $startDate;
public $endDate;

public $startTime;
public $endTime;
    public $shift;
    public $frontdesk;
    public $frontdesk_name;

    public $transactions;
    public array $groups = [];

    public array $salesRooms = [];
    public array $salesRoomsRaw = [];

    public array $summary = [];

    public $expensesRows;
    public float $expensesTotal = 0;

    public array $roomSummary = [];

    public function mount()
    {
        $this->summary = [];
        $this->transactions = collect();
        $this->totalSales = 0;
        $this->groups = [];
        $this->salesRooms = [];
        $this->salesRoomsRaw = [];
        $this->expensesRows = collect();
        $this->expensesTotal = 0;
        $this->roomSummary = [];

        $this->date_from = now()->toDateString();
        $this->date_to = now()->toDateString();

        $this->time_from = '08:00';
        $this->time_to = '20:00';

        $this->generateReport();
    }

    public function updatedShowExtend()   { $this->recomputeTotals(); }
    public function updatedShowAmenities(){ $this->recomputeTotals(); }
    public function updatedShowFood()     { $this->recomputeTotals(); }
    public function updatedShowDamages()  { $this->recomputeTotals(); }
    public function updatedShowTransfer() { $this->recomputeTotals(); }
    public function updatedShowDeposits() { $this->recomputeTotals(); }

    private function recomputeTotals(): void
    {
        $this->applySalesVisibilityAndTotals();
    }

    private function applySalesVisibilityAndTotals(): void
    {
        $rooms = [];
        $grandTotal = 0;

        foreach ($this->salesRoomsRaw as $room) {
            $visibleRows = collect($room['rows'])
                ->filter(function ($row) {
                    return match ($row['row_type']) {
                        'room_amount' => true,
                        'extend'      => $this->showExtend,
                        'amenities'   => $this->showAmenities,
                        'food'        => $this->showFood,
                        'damages'     => $this->showDamages,
                        'transfer'    => $this->showTransfer,
                        'deposit' => $this->showDeposits,
                        default       => true,
                    };
                })
                ->values()
                ->all();

            if (count($visibleRows) > 0) {
                foreach ($visibleRows as $idx => $row) {
                    $lineTotal =
                        (float) $row['room_amount']
                        + (float) $row['extend_amount']
                        + (float) $row['amenities_amount']
                        + (float) $row['food_amount']
                        + (float) $row['damages_amount']
                        + (float) $row['transfer_amount'];
                        + (float) $row['deposit_amount'];

                    $visibleRows[$idx]['total'] = $lineTotal;
                    $grandTotal += $lineTotal;
                }

                $room['rows'] = $visibleRows;
                $room['rowspan'] = count($visibleRows);
                $rooms[] = $room;
            }
        }

        $this->salesRooms = $rooms;
        $this->totalSales = $grandTotal;
    }

    private function applySalesTypeFilter($query, string $column = 'created_at')
    {
        switch ($this->type) {
            case 'Daily':
                $query->whereDate($column, now()->toDateString());
                break;

            case 'Weekly':
                $query->whereBetween($column, [
                    now()->copy()->startOfWeek(),
                    now()->copy()->endOfWeek(),
                ]);
                break;

            case 'Monthly':
                $query->whereMonth($column, now()->month)
                      ->whereYear($column, now()->year);
                break;

            default:
                break;
        }

        return $query;
    }

    private function applyTransactionDateFilters($query, string $column = 'created_at')
    {
        $query->when($this->date_from, fn($q, $d) => $q->whereDate($column, '>=', $d))
              ->when($this->date_to, fn($q, $d) => $q->whereDate($column, '<=', $d));

        $this->applySalesTypeFilter($query, $column);

        return $query;
    }

    private function applyShiftFilter($query)
    {
        if (!$this->shift) {
            return;
        }

        $query->where('shift', $this->shift);
    }

    private function shiftWindow(): array
    {
        $fromDate = $this->date_from ? Carbon::parse($this->date_from) : now();
        $toDate   = $this->date_to ? Carbon::parse($this->date_to) : $fromDate;

        if (!$this->shift) {
            return [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()];
        }

        if ($this->shift === 'AM') {
            return [$fromDate->copy()->setTime(8, 0, 0), $toDate->copy()->setTime(20, 0, 0)];
        }

        return [$fromDate->copy()->setTime(20, 0, 0), $toDate->copy()->addDay()->setTime(8, 0, 0)];
    }

    public function render()
    {
        return view('livewire.back-office.sales-report', [
            'frontdesks' => Frontdesk::where('branch_id', auth()->user()->branch_id)->get(),
        ]);
    }

    public function generateReport()
    {
        $frontdesk = Frontdesk::find($this->frontdesk);
        $this->frontdesk_name = $frontdesk?->name;

        // Get filtered transaction scope first
        $txScope = Transaction::query()
            ->whereNotNull('checkin_detail_id')
            ->whereHas('room', fn($q) => $q->where('branch_id', auth()->user()->branch_id));

        $this->applyTransactionDateFilters($txScope, 'created_at');
        $this->applyShiftFilter($txScope);

        $detailIds = (clone $txScope)
            ->when($this->frontdesk, function ($q, $f) {
                $q->whereHas('checkin_details', fn($q2) => $q2->where('frontdesk_id', $f));
            })
            ->distinct()
            ->pluck('checkin_detail_id')
            ->filter()
            ->values();

        $details = CheckinDetail::query()
            ->with([
                'room.type',
                'guest',
                'rate',
                'frontdesk',
            ])
            ->whereIn('id', $detailIds)
            ->whereHas('room', fn($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->orderBy('room_id')
            ->orderBy('check_in_at')
            ->orderBy('check_out_at')
            ->get();

        $frontdeskMap = Frontdesk::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->pluck('name', 'id');

        // include base room tx type too so base row can display transaction shift
        $txRows = Transaction::query()
                ->with('shift_log')
            ->whereIn('checkin_detail_id', $detailIds)
            ->whereIn('transaction_type_id', [1, 2, 4, 6, 7, 8, 9])
            ->select([
                'id',
                'checkin_detail_id',
                'transaction_type_id',
                'payable_amount',
                'assigned_frontdesk_id',
                'created_at',
                'shift',
                'remarks',
            ]);


        $this->applyTransactionDateFilters($txRows, 'created_at');
        $this->applyShiftFilter($txRows);

        $txRows = $txRows
            ->orderBy('created_at')
            ->get()
            ->map(function ($tx) use ($frontdeskMap) {
                $frontdeskId = $this->extractAssignedFrontdeskId($tx->assigned_frontdesk_id);
                $tx->remarks != 'Deposit From Check In (Room Key & TV Remote)';
                $tx->resolved_frontdesk_id = $frontdeskId;
                $tx->resolved_frontdesk_name = $frontdeskId
                    ? strtoupper($frontdeskMap[$frontdeskId] ?? '—')
                    : '—';

                $tx->resolved_shift = strtoupper($tx->shift ?? '—');

                return $tx;
            })
            ->groupBy('checkin_detail_id');

        // $this->salesRoomsRaw = $this->buildSalesRooms($details, $txRows);
        // $this->applySalesVisibilityAndTotals();
             $this->startDateTime = null;
            $this->endDateTime = null;

        // Ensure time format works with SQL TIME()
            if ($this->time_from) {
                $this->startTime = $this->time_from . ':00';
            }

            if ($this->time_to) {
                $this->endTime = $this->time_to . ':59';
            }

            // Date filters
            $this->startDate = $this->date_from;
            $this->endDate = $this->date_to;

            $this->salesRooms = $this->buildSalesRows();

            $this->totalSales = collect($this->salesRooms)->sum('total');


        $this->summary = $this->buildSummary();
        $this->buildExpensesSummary();
        $this->buildRoomSummary();
    }

    private function extractAssignedFrontdeskId($value): ?int
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            return isset($value[0]) && is_numeric($value[0]) ? (int) $value[0] : null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return isset($decoded[0]) && is_numeric($decoded[0]) ? (int) $decoded[0] : null;
            }

            if (preg_match('/\d+/', $value, $matches)) {
                return (int) $matches[0];
            }
        }

        return null;
    }

private function buildSalesRows(): array
{

    $rows = DB::table('transactions as tr')
        ->join('shift_logs as sl', 'sl.id', '=', 'tr.shift_log_id')

        ->leftJoin('checkin_details as cd', 'cd.id', '=', 'tr.checkin_detail_id')
        ->leftJoin('check_out_guest_reports as cg', 'cg.checkin_details_id', '=', 'cd.id')
        ->leftJoin('frontdesks as fci', 'fci.id', '=', 'cd.frontdesk_id')
        ->leftJoin('frontdesks as fco', 'fco.id', '=', 'cg.frontdesk_id')
        ->leftJoin('guests as g', 'g.id', '=', 'tr.guest_id')
        ->leftJoin('rooms as r', 'r.id', '=', 'tr.room_id')
        ->leftJoin('types as t', 't.id', '=', 'r.type_id')
        ->leftJoin('transaction_types as tt', 'tt.id', '=', 'tr.transaction_type_id')
        ->leftJoin('users as u', 'u.id', '=', 'sl.frontdesk_id')
        ->when($this->startDate && $this->endDate, function ($query) {

            $query->whereBetween(DB::raw('DATE(tr.created_at)'), [
                $this->startDate,
                $this->endDate
            ]);

        })
        ->when($this->frontdesk, function ($query) {
            $query->where('u.id', $this->frontdesk);
        })
        // ->when($this->startTime && $this->endTime, function ($query) {

        //     $query->whereBetween(DB::raw('TIME(tr.created_at)'), [
        //         $this->startTime,
        //         $this->endTime
        //     ]);

        // })
->selectRaw('
    r.number as room_no,
    t.name as room_type,
    g.name as guest_name,
    cd.check_in_at,
    cd.check_out_at,
    cd.hours_stayed as initial_hrs,

    CASE WHEN tt.name = "Check In"
        THEN tr.payable_amount ELSE 0 END as room_amount,

    CASE WHEN tt.name = "Extend"
        THEN tr.payable_amount ELSE 0 END as extend_amount,

    CASE WHEN tt.name = "Amenities"
        THEN tr.payable_amount ELSE 0 END as amenities_amount,

    CASE WHEN tt.name = "Food and Beverages"
        THEN tr.payable_amount ELSE 0 END as food_amount,

    CASE WHEN tt.name = "Damage Charges"
        THEN tr.payable_amount ELSE 0 END as damages_amount,

    CASE WHEN tt.name = "Transfer Room"
        THEN tr.payable_amount ELSE 0 END as transfer_amount,

    CASE
        WHEN tt.name = "Deposit"
        AND tr.remarks = "Deposit From Check In (Room Key & TV Remote)"
        AND (
            cg.created_at IS NULL
            OR DATE(cg.created_at) NOT BETWEEN "' . $this->startDate . '" AND "' . $this->endDate . '"
        )
        THEN tr.payable_amount
        ELSE 0
    END as room_deposit,

    CASE
        WHEN tt.name = "Deposit"
        AND (
            tr.remarks IS NULL
            OR tr.remarks != "Deposit From Check In (Room Key & TV Remote)"
        )
         AND (
            cg.created_at IS NULL
            OR DATE(cg.created_at) NOT BETWEEN "' . $this->startDate . '" AND "' . $this->endDate . '"
        )
        THEN tr.payable_amount
        ELSE 0
    END as client_deposit,

    CASE
        WHEN tr.transaction_type_id NOT IN (2, 5)
        THEN tr.payable_amount
        ELSE 0
    END as total,
    u.name,
    tt.name as transaction_type,
    tr.created_at
')

->orderBy('r.id')
->orderBy('tr.created_at')

        ->get()

        ->map(function ($r) {

            return [
                'room_number' => $r->room_no,
                'room_type' => $r->room_type ?? '—',
                'guest_name' => strtoupper($r->guest_name ?? '—'),
                'type' => $r->transaction_type,
                'check_in' => $r->check_in_at
                    ? \Carbon\Carbon::parse($r->check_in_at)->format('m-d-Y h:iA')
                    : '—',

                'check_out' => $r->check_out_at
                    ? \Carbon\Carbon::parse($r->check_out_at)->format('m-d-Y h:iA')
                    : '—',

                'initial_hrs' => $r->initial_hrs
                    ? $r->initial_hrs . ' hrs'
                    : '—',

                'room_amount' => (float) $r->room_amount,
                'extend_amount' => (float) $r->extend_amount,
                'amenities_amount' => (float) $r->amenities_amount,
                'food_amount' => (float) $r->food_amount,
                'damages_amount' => (float) $r->damages_amount,
                'transfer_amount' => (float) $r->transfer_amount,

                'room_deposit' => (float) $r->room_deposit,
                'client_deposit' => (float) $r->client_deposit,

                'total' => (float) $r->total,
                'created_at' => $r->created_at
                    ? \Carbon\Carbon::parse($r->created_at)->format('m-d-Y h:iA')
                    : '—',
                'frontdesk' => $r->name,
            ];
        })

        ->toArray();

    return $rows;
}

    private function buildSalesRooms($details, $txRowsByDetail): array
    {
        $rooms = [];

        $groupedByRoom = $details
            ->groupBy('room_id')
            ->sortBy(function ($items) {
                return (int) ($items->first()?->room?->number ?? PHP_INT_MAX);
            });

        foreach ($groupedByRoom as $roomId => $roomDetails) {
            $room = $roomDetails->first()?->room;
            $roomRows = [];

            foreach ($roomDetails as $detail) {
                $guest = $detail->guest;
                $rate = $detail->rate;
                $frontdesk = $detail->frontdesk;

                $baseGuestName = strtoupper($guest?->name ?? '—');

                $roomAmount = $guest?->is_long_stay
                    ? (float) ($rate?->amount ?? 0) * (int) ($guest?->number_of_days ?? 1)
                    : (float) ($rate?->amount ?? 0);

                $detailTxs = collect($txRowsByDetail[$detail->id] ?? []);

                $baseTx = $detailTxs
    ->where('transaction_type_id', 1)
    ->sortBy('created_at')
    ->first();

$meta = [
    'room_type'      => $room?->type?->name ?? '—',
    'guest_name'     => $baseGuestName,
    'check_in'       => $detail->check_in_at ? Carbon::parse($detail->check_in_at)->format('m-d-Y h:iA') : '—',
    'check_out'      => $detail->check_out_at ? Carbon::parse($detail->check_out_at)->format('m-d-Y h:iA') : '—',
    // hours_stayed is already stored as 24 × number_of_days for long-stays
    // (see CheckInFromKiosk / RoomMonitoring save paths). Multiplying by
    // number_of_days again here doubles the displayed value. Same fix as
    // commit 1f2025d which corrected the other SalesReport sites.
    'initial_hrs'    => $detail->hours_stayed
        ? ($detail->hours_stayed . ' hrs')
        : '—',
    'frontdesk_name' => strtoupper($frontdesk?->name ?? '—'),
];

/*
|--------------------------------------------------------------------------
| Base room row
|--------------------------------------------------------------------------
| Show it only if:
| - no shift filter is selected, OR
| - the filtered transactions still contain the base/check-in tx
|--------------------------------------------------------------------------
*/
if (!$this->shift || $baseTx) {
    $roomRows[] = [
        'detail_id'         => $detail->id,
        'row_type'          => 'room_amount',
        'number'            => $room?->number ?? '—',
        'room_type'         => $meta['room_type'],
        'guest_name'        => $meta['guest_name'],
        'check_in'          => $meta['check_in'],
        'check_out'         => $meta['check_out'],
        'initial_hrs'       => $meta['initial_hrs'],
        'room_amount'       => $roomAmount,
        'extend_amount'     => 0,
        'amenities_amount'  => 0,
        'food_amount'       => 0,
        'damages_amount'    => 0,
        'transfer_amount'   => 0,
        'deposit_amount' => 0,
        'frontdesk_name'    => $baseTx
            ? strtoupper($baseTx->resolved_frontdesk_name ?? $meta['frontdesk_name'])
            : $meta['frontdesk_name'],
        'shift'             => $baseTx
            ? strtoupper($baseTx->resolved_shift ?? '—')
            : '—',
        'total'             => $roomAmount,
    ];
}

                // $baseTx = $detailTxs
                //     ->where('transaction_type_id', 1)
                //     ->sortBy('created_at')
                //     ->first();

                // $defaultShift = $baseTx?->resolved_shift
                //     ?? optional($detailTxs->sortBy('created_at')->first())->resolved_shift
                //     ?? '—';

                // $meta = [
                //     'room_type'      => $room?->type?->name ?? '—',
                //     'guest_name'     => $baseGuestName,
                //     'check_in'       => $detail->check_in_at ? Carbon::parse($detail->check_in_at)->format('m-d-Y h:iA') : '—',
                //     'check_out'      => $detail->check_out_at ? Carbon::parse($detail->check_out_at)->format('m-d-Y h:iA') : '—',
                //     'initial_hrs'    => $detail->hours_stayed
                //         ? ($guest?->is_long_stay
                //             ? (($detail->hours_stayed * (int) ($guest?->number_of_days ?? 1)) . ' hrs')
                //             : ($detail->hours_stayed . ' hrs'))
                //         : '—',
                //     'frontdesk_name' => strtoupper($frontdesk?->name ?? '—'),
                //     'shift'          => $defaultShift,
                // ];

                // // Base room row
                // $roomRows[] = [
                //     'detail_id'         => $detail->id,
                //     'row_type'          => 'room_amount',
                //     'number'            => $room?->number ?? '—',
                //     'room_type'         => $meta['room_type'],
                //     'guest_name'        => $meta['guest_name'],
                //     'check_in'          => $meta['check_in'],
                //     'check_out'         => $meta['check_out'],
                //     'initial_hrs'       => $meta['initial_hrs'],
                //     'room_amount'       => $roomAmount,
                //     'extend_amount'     => 0,
                //     'amenities_amount'  => 0,
                //     'food_amount'       => 0,
                //     'damages_amount'    => 0,
                //     'transfer_amount'   => 0,
                //     'frontdesk_name'    => $meta['frontdesk_name'],
                //     'shift'             => $meta['shift'],
                //     'total'             => $roomAmount,
                // ];

              $extendTxs = $detailTxs
    ->where('transaction_type_id', 6)
    ->sortBy('created_at')
    ->values();

$extendAmount = (float) $extendTxs->sum('payable_amount');
$extendFirstTx = $extendTxs->first();

if ($extendAmount > 0) {
    $roomRows[] = [
        'detail_id'         => $detail->id,
        'row_type'          => 'extend',
        'number'            => $room?->number ?? '—',
        'room_type'         => $meta['room_type'],
        'guest_name'        => $meta['guest_name'],
        'check_in'          => $meta['check_in'],
        'check_out'         => $meta['check_out'],
        'initial_hrs'       => $meta['initial_hrs'],
        'room_amount'       => 0,
        'extend_amount'     => $extendAmount,
        'amenities_amount'  => 0,
        'food_amount'       => 0,
        'damages_amount'    => 0,
        'transfer_amount'   => 0,
        'deposit_amount' => 0,
        'frontdesk_name'    => strtoupper($extendFirstTx?->resolved_frontdesk_name ?? '—'),
        'shift'             => strtoupper($extendFirstTx?->resolved_shift ?? '—'),
        'total'             => $extendAmount,
    ];
}

                $amenitiesTxs = $detailTxs
    ->where('transaction_type_id', 8)
    ->sortBy('created_at')
    ->values();

$amenitiesAmount = (float) $amenitiesTxs->sum('payable_amount');
$amenitiesFirstTx = $amenitiesTxs->first();

if ($amenitiesAmount > 0) {
    $roomRows[] = [
        'detail_id'         => $detail->id,
        'row_type'          => 'amenities',
        'number'            => $room?->number ?? '—',
        'room_type'         => $meta['room_type'],
        'guest_name'        => $meta['guest_name'],
        'check_in'          => $meta['check_in'],
        'check_out'         => $meta['check_out'],
        'initial_hrs'       => $meta['initial_hrs'],
        'room_amount'       => 0,
        'extend_amount'     => 0,
        'amenities_amount'  => $amenitiesAmount,
        'food_amount'       => 0,
        'damages_amount'    => 0,
        'transfer_amount'   => 0,
        'deposit_amount' => 0,
        'frontdesk_name'    => strtoupper($amenitiesFirstTx?->resolved_frontdesk_name ?? '—'),
        'shift'             => strtoupper($amenitiesFirstTx?->resolved_shift ?? '—'),
        'total'             => $amenitiesAmount,
    ];
}

                $foodTxs = $detailTxs
    ->where('transaction_type_id', 9)
    ->sortBy('created_at')
    ->values();

$foodAmount = (float) $foodTxs->sum('payable_amount');
$foodFirstTx = $foodTxs->first();

if ($foodAmount > 0) {
    $roomRows[] = [
        'detail_id'         => $detail->id,
        'row_type'          => 'food',
        'number'            => $room?->number ?? '—',
        'room_type'         => $meta['room_type'],
        'guest_name'        => $meta['guest_name'],
        'check_in'          => $meta['check_in'],
        'check_out'         => $meta['check_out'],
        'initial_hrs'       => $meta['initial_hrs'],
        'room_amount'       => 0,
        'extend_amount'     => 0,
        'amenities_amount'  => 0,
        'food_amount'       => $foodAmount,
        'damages_amount'    => 0,
        'transfer_amount'   => 0,
        'deposit_amount' => 0,
        'frontdesk_name'    => strtoupper($foodFirstTx?->resolved_frontdesk_name ?? '—'),
        'shift'             => strtoupper($foodFirstTx?->resolved_shift ?? '—'),
        'total'             => $foodAmount,
    ];
}

               $damagesTxs = $detailTxs
    ->where('transaction_type_id', 4)
    ->sortBy('created_at')
    ->values();

$damagesAmount = (float) $damagesTxs->sum('payable_amount');
$damagesFirstTx = $damagesTxs->first();

if ($damagesAmount > 0) {
    $roomRows[] = [
        'detail_id'         => $detail->id,
        'row_type'          => 'damages',
        'number'            => $room?->number ?? '—',
        'room_type'         => $meta['room_type'],
        'guest_name'        => $meta['guest_name'],
        'check_in'          => $meta['check_in'],
        'check_out'         => $meta['check_out'],
        'initial_hrs'       => $meta['initial_hrs'],
        'room_amount'       => 0,
        'extend_amount'     => 0,
        'amenities_amount'  => 0,
        'food_amount'       => 0,
        'damages_amount'    => $damagesAmount,
        'transfer_amount'   => 0,
        'deposit_amount' => 0,
        'frontdesk_name'    => strtoupper($damagesFirstTx?->resolved_frontdesk_name ?? '—'),
        'shift'             => strtoupper($damagesFirstTx?->resolved_shift ?? '—'),
        'total'             => $damagesAmount,
    ];
}

                $transferTxs = $detailTxs
    ->where('transaction_type_id', 7)
    ->sortBy('created_at')
    ->values();

$transferAmount = (float) $transferTxs->sum('payable_amount');
$transferFirstTx = $transferTxs->first();

if ($transferAmount > 0) {
    $roomRows[] = [
        'detail_id'         => $detail->id,
        'row_type'          => 'transfer',
        'number'            => $room?->number ?? '—',
        'room_type'         => $meta['room_type'],
        'guest_name'        => $meta['guest_name'],
        'check_in'          => $meta['check_in'],
        'check_out'         => $meta['check_out'],
        'initial_hrs'       => $meta['initial_hrs'],
        'room_amount'       => 0,
        'extend_amount'     => 0,
        'amenities_amount'  => 0,
        'food_amount'       => 0,
        'damages_amount'    => 0,
        'transfer_amount'   => $transferAmount,
        'deposit_amount' => 0,
        'frontdesk_name'    => strtoupper($transferFirstTx?->resolved_frontdesk_name ?? '—'),
        'shift'             => strtoupper($transferFirstTx?->resolved_shift ?? '—'),
        'total'             => $transferAmount,
    ];
}
$depositTxs = $detailTxs
    ->where('transaction_type_id', 2)
    ->filter(function ($tx) {
        return trim((string) $tx->remarks) !== 'Deposit From Check In (Room Key & TV Remote)';
    })
    ->sortBy('created_at')
    ->values();

$depositAmount = (float) $depositTxs->sum('payable_amount');
$depositFirstTx = $depositTxs->first();

if ($depositAmount > 0) {
    $roomRows[] = [
        'detail_id'         => $detail->id,
        'row_type'          => 'deposit',
        'number'            => $room?->number ?? '—',
        'room_type'         => $meta['room_type'],
        'guest_name'        => $meta['guest_name'],
        'check_in'          => $meta['check_in'],
        'check_out'         => $meta['check_out'],
        'initial_hrs'       => $meta['initial_hrs'],
        'room_amount'       => 0,
        'extend_amount'     => 0,
        'amenities_amount'  => 0,
        'food_amount'       => 0,
        'damages_amount'    => 0,
        'transfer_amount'   => 0,
        'deposit_amount'    => $depositAmount,
        'frontdesk_name'    => strtoupper($depositFirstTx?->resolved_frontdesk_name ?? '—'),
        'shift'             => strtoupper($depositFirstTx?->resolved_shift ?? '—'),
        'total'             => $depositAmount,
    ];
}
            }

            if (count($roomRows) > 0) {
                $rooms[] = [
                    'room_id'     => $roomId,
                    'room_number' => $room?->number ?? '—',
                    'rowspan'     => count($roomRows),
                    'rows'        => $roomRows,
                ];
            }
        }

        return $rooms;
    }

   private function buildSummary(): array
{
    $tx = Transaction::query()
        ->whereNotNull('checkin_detail_id')
        ->with(['checkin_details.room.type', 'checkin_details.guest'])
        ->whereHas('room', fn($q) => $q->where('branch_id', auth()->user()->branch_id));

    $this->applyTransactionDateFilters($tx, 'created_at');
    $this->applyShiftFilter($tx);

    $tx->when($this->frontdesk, function ($q, $f) {
        $q->whereHas('checkin_details', fn($q2) => $q2->where('frontdesk_id', $f));
    });

    $checkinIds = (clone $tx)->distinct()->pluck('checkin_detail_id')->values();

    // Exclude transferred-room stays from summary counts
    $transferredCheckinIds = Transaction::query()
        ->where('transaction_type_id', 7)
        ->whereIn('checkin_detail_id', $checkinIds)
        ->distinct()
        ->pluck('checkin_detail_id');

    $effectiveCheckinIds = $checkinIds->diff($transferredCheckinIds)->values();

    $details = CheckinDetail::query()
        ->whereIn('id', $effectiveCheckinIds)
        ->with(['room.type', 'guest'])
        ->get();

    $guestCounts = $details
        ->groupBy(fn($d) => $d->room?->type?->name ?? 'Unknown')
        ->map(function ($items) {
            return $items->pluck('guest_id')->filter()->unique()->count();
        });

    $allTypes = \App\Models\Type::where('branch_id', auth()->user()->branch_id)->get();

    $guestPerType = $allTypes->map(function ($type) use ($guestCounts) {
        return [
            'label' => $type->name,
            'value' => (int) ($guestCounts[$type->name] ?? 0),
        ];
    })->values()->all();

    [$start, $end] = $this->shiftWindow();

    $occupiedRoomIds = CheckinDetail::query()
        ->when($this->frontdesk, fn($q, $f) => $q->where('frontdesk_id', $f))
        ->whereHas('room', fn($q) => $q->where('branch_id', auth()->user()->branch_id))
        ->where(function ($q) use ($start, $end) {
            $q->where('check_in_at', '<', $end)
              ->where(function ($q2) use ($start) {
                  $q2->whereNull('check_out_at')
                     ->orWhere('check_out_at', '>', $start);
              });
        })
        ->distinct()
        ->pluck('room_id');

    $allRooms = \App\Models\Room::query()
        ->where('branch_id', auth()->user()->branch_id)
        ->with('type')
        ->get();

    $unoccupiedRooms = $allRooms->whereNotIn('id', $occupiedRoomIds);

    $unoccupiedCounts = $unoccupiedRooms
        ->groupBy(fn($r) => $r->type?->name ?? 'Unknown')
        ->map(fn($items) => $items->count());

    $unoccupiedPerType = $allTypes->map(function ($type) use ($unoccupiedCounts) {
        return [
            'label' => $type->name,
            'value' => (int) ($unoccupiedCounts[$type->name] ?? 0),
        ];
    })->values()->all();

    $damagedRoomIds = Transaction::query()
        ->where('transaction_type_id', 4)
        ->whereIn('checkin_detail_id', $effectiveCheckinIds)
        ->distinct()
        ->pluck('room_id');

    $damagedRooms = \App\Models\Room::query()
        ->where('branch_id', auth()->user()->branch_id)
        ->whereIn('id', $damagedRoomIds)
        ->with('type')
        ->get()
        ->groupBy(fn($r) => $r->type?->name ?? 'Unknown')
        ->map(fn($items) => $items->count());

    $underRepairCounts = $allTypes->map(function ($type) use ($damagedRooms) {
        return [
            'label' => $type->name,
            'value' => (int) ($damagedRooms[$type->name] ?? 0),
        ];
    })->values()->all();

   $checkinBuckets  = $this->twoShiftBucketsForFilteredDetails($effectiveCheckinIds, 'check_in_at', false);
$checkoutBuckets = $this->twoShiftBucketsForFilteredDetails($effectiveCheckinIds, 'check_out_at', true);
    return [
        'guest_per_accommodation' => $guestPerType,
        'unoccupied_rooms'        => $unoccupiedPerType,
        'under_repair_rooms'      => $underRepairCounts,
        'group_checkin_time'      => $checkinBuckets,
        'group_checkout_time'     => $checkoutBuckets,
    ];
}

private function twoShiftBucketsForFilteredDetails($checkinIds, string $column, bool $requireIsCheckout = false): array
{
    $q = CheckinDetail::query()
        ->whereIn('id', $checkinIds)
        ->whereHas('room', fn($q) => $q->where('branch_id', auth()->user()->branch_id))
        ->when($requireIsCheckout, fn($q) => $q->where('is_check_out', true))
        ->whereNotNull($column);

    $am = (clone $q)
        ->whereTime(DB::raw("TIME($column)"), '>=', '08:00:00')
        ->whereTime(DB::raw("TIME($column)"), '<', '20:00:00')
        ->count();

    $pm = (clone $q)
        ->where(function ($sub) use ($column) {
            $sub->whereTime(DB::raw("TIME($column)"), '>=', '20:00:00')
                ->orWhereTime(DB::raw("TIME($column)"), '<', '08:00:00');
        })
        ->count();

    return [
        ['label' => '8:00 AM - 8:00 PM', 'value' => (int) $am],
        ['label' => '8:00 PM - 8:00 AM', 'value' => (int) $pm],
    ];
}

    public function resetFilters()
    {
        $this->reset(['frontdesk', 'type', 'date_from', 'date_to', 'shift']);
        $this->transactions = collect();
        $this->groups = [];
        $this->salesRooms = [];
        $this->salesRoomsRaw = [];
        $this->totalSales = 0;

        $this->type = 'Overall Sales';
        $this->generateReport();
    }

    private function buildExpensesSummary(): void
    {
        [$start, $end] = $this->shiftWindow();

        $rows = \App\Models\Expense::query()
            ->with(['expenseCategory', 'user'])
            ->whereBetween('created_at', [$start, $end])
            ->when($this->shift, fn($q) => $q->where('shift', $this->shift))
            ->when($this->frontdesk, fn($q) => $q->where('user_id', $this->frontdesk))
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($e) {
                return [
                    'expense_type' => $e->category?->name ?? '—',
                    'description'  => $e->description ?? '—',
                    'shift'        => $e->shift ?? '—',
                    'amount'       => (float) $e->amount,
                    'frontdesk'    => $e->user?->name ?? '—',
                ];
            });

        $this->expensesRows  = $rows;
        $this->expensesTotal = (float) $rows->sum('amount');
    }

    private function buildRoomSummary(): void
    {
        $floors = \App\Models\Floor::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->orderBy('number')
            ->get();

        $transactions = Transaction::query()
            ->with(['room.floor', 'transaction_type'])
            ->whereNotNull('checkin_detail_id')
            ->whereHas('room', fn($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->whereNotIn('transaction_type_id', [2, 5]);

        $this->applyTransactionDateFilters($transactions, 'created_at');
        $this->applyShiftFilter($transactions);

        $transactions = $transactions
            ->when($this->frontdesk, function ($q, $f) {
                $q->whereHas('checkin_details', fn($q2) => $q2->where('frontdesk_id', $f));
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
                'floors'      => $rowPerFloor,
                'row_total'   => $rowTotal,
            ];
        }

        $this->roomSummary = [
            'floors' => $floors->map(fn($f) => [
                'id'     => $f->id,
                'number' => $f->number,
            ])->all(),
            'rows'   => $tableRows,
            'totals' => $totalsPerFloor,
        ];
    }

    public function printPdf()
    {
    }
}
