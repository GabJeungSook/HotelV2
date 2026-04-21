<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use App\Models\ExtendedGuestReport as ExtendedGuestReportModel;
use App\Models\Room;
use App\Models\Frontdesk;
use Carbon\Carbon;

class ExtendedGuestReport extends Component
{
    // public $only = [];
    // public $frontdesks;
    // public $frontdesk_id, $shift, $date, $time;
    // public $total_guest;

     public $shift = '';      // AM / PM / ''
    public $date = '';       // YYYY-MM-DD / ''
    public $room_id = '';    // room id / ''

    protected $queryString = [
        'shift' => ['except' => ''],
        'date' => ['except' => ''],
        'room_id' => ['except' => ''],
    ];

    public function mount()
    {
        if ($this->date === '') {
            $this->date = now()->toDateString();
        }
    }

     public function resetFilters()
    {
        $this->reset(['shift', 'room_id']);
        $this->date = now()->toDateString();
    }

     public function getRoomsProperty()
    {
        // Adjust select columns to match your rooms table.
        return Room::query()
            ->select('id', 'number')
            ->orderBy('number')
            ->get();
    }

    // public function mount()
    // {
    //     $this->total_guest = guestReport::where(
    //         'branch_id',
    //         auth()->user()->branch_id
    //     )->count();
    //     $this->only = guestReport::pluck('room_id')->toArray();
    //     $this->frontdesks = Frontdesk::where(
    //         'branch_id',
    //         auth()->user()->branch_id
    //     )->get();
    // }


    public function getGroupsProperty()
    {
        $reports = ExtendedGuestReportModel::query()
            ->with([
                // Adjust if your room fields differ
                'room:id,number,type_id',
                'room.type:id,name',
            ])
            ->when($this->shift !== '', fn ($q) => $q->where('shift', $this->shift))
            ->when($this->date !== '', fn ($q) => $q->whereDate('created_at', $this->date))
            ->when($this->room_id !== '', fn ($q) => $q->where('room_id', $this->room_id))
            ->orderBy('shift')
            ->orderBy('created_at')
            ->get();

        // Sheet grouping: SHIFT + DATE (created_at)
        $sheetGroups = $reports->groupBy(function ($r) {
            $shift = $r->shift ?? 'UNKNOWN';
            $date  = $r->created_at ? Carbon::parse($r->created_at)->toDateString() : 'UNKNOWN_DATE';
            return $shift . '|' . $date;
        });

        return $sheetGroups->map(function ($items, $key) {
            [$shift, $dateStr] = explode('|', $key);

            $dateLabel = $dateStr !== 'UNKNOWN_DATE'
                ? Carbon::parse($dateStr)->format('F d, Y')
                : '—';

            // Within each sheet block: group by room + checkin_details_id
            $rows = $items->groupBy(function ($r) {
                return $r->room_id . '|' . $r->checkin_details_id;
            })->map(function ($roomCheckinItems) {

                $sorted = $roomCheckinItems->sortBy('created_at')->values();

                $roomNo   = $sorted->first()->room?->number ?? '—';
                $type = $sorted->first()->room?->type?->name ?? '—';

                // No. Hrs = total_hours per row
                $entries = $sorted->map(function ($rec) {
                    $dateTime = $rec->created_at
                        ? Carbon::parse($rec->created_at)->format('F d, Y h:i a')
                        : '—';

                    return [
                        'date_time' => $dateTime,
                        'no_hrs'    => ((int) $rec->total_hours) . ' Hrs',
                    ];
                })->values()->all();

                // Total Hrs = sum of total_hours in this group
                $totalHrs = $sorted->sum(fn ($rec) => (int) $rec->total_hours);

                return [
                    'number'   => $roomNo,
                    'room_type' => $type,
                    'entries'   => $entries,
                    'total_hrs' => $totalHrs . ' hrs',
                ];
            })->values()->all();

            return [
                'label'      => strtoupper($shift) . ' SHIFT',
                'date_label' => $dateLabel,
                'rows'       => $rows,
            ];
        })->values()->all();
    }

    public function render()
    {
        return view('livewire.back-office.reports.extended-guest-report', [
            'groups' => $this->groups,
            'rooms'  => $this->rooms,
        ]);
    }
}


    // public function render()
    // {
    //     return view('livewire.back-office.reports.extended-guest-report', [
    //         'rooms' => Room::whereIn('id', $this->only)
    //             ->where('branch_id', auth()->user()->branch_id)
    //             ->with('extendedGuestReports')
    //             ->when($this->frontdesk_id, function ($query) {
    //                 $query->whereHas('extendedGuestReports', function ($query) {
    //                     $query->where('frontdesk_id', $this->frontdesk_id);
    //                 });
    //             })
    //             ->when($this->shift, function ($query) {
    //                 $query->whereHas('extendedGuestReports', function ($query) {
    //                     $query->where('shift', $this->shift);
    //                 });
    //             })
    //             ->when($this->date, function ($query) {
    //                 $query->whereHas('extendedGuestReports', function ($query) {
    //                     $query->whereDate('created_at', $this->date);
    //                 });
    //             })
    //             ->when($this->time, function ($query) {
    //                 $query->whereHas('extendedGuestReports', function ($query) {
    //                     $query->whereHas('checkinDetail', function ($query) {
    //                         $query
    //                             ->whereTime('created_at', '>=', '08:00:00')
    //                             ->whereTime('created_at', '<=', $this->time);
    //                     });
    //                 });
    //             })
    //             ->orderBy('number', 'asc')
    //             ->get(),
    //     ]);
    // }

