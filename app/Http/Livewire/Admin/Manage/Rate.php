<?php

namespace App\Http\Livewire\Admin\Manage;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Type;
use Livewire\Component;
use WireUi\Traits\Actions;
use App\Models\StayingHour;
use App\Models\Rate as rateModel;

class Rate extends Component
{
    use Actions;

    // Filters
    public $search = '';
    public $filter_type_id = '';
    public $filter_floor_id = '';
    public $branch_id;
    public $branch_name;

    // Selection
    public $selected_rooms = [];
    public $select_all = false;

    // Rate modal
    public $rate_modal = false;
    public $rate_amounts = [];

    // Staying hour modal
    public $add_staying_hour_modal = false;
    public $number;

    public function render()
    {
        return view('livewire.admin.manage.rate', [
            'rooms' => $this->getRooms(),
            'stayingHours' => StayingHour::where('branch_id', $this->branch_id)->orderBy('number')->get(),
            'branches' => Branch::all(),
            'types' => Type::where('branch_id', $this->branch_id)->get(),
            'floors' => Floor::where('branch_id', $this->branch_id)->get(),
        ]);
    }

    public function mount()
    {
        if (auth()->user()->hasRole('admin')) {
            $this->branch_id = auth()->user()->branch_id;
            $this->branch_name = Branch::where('id', $this->branch_id)->value('name');
        }
    }

    public function updatedBranchId()
    {
        $this->branch_name = Branch::where('id', $this->branch_id)->value('name');
        $this->selected_rooms = [];
        $this->select_all = false;
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selected_rooms = $this->getRooms()->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selected_rooms = [];
        }
    }

    public function updatedFilterTypeId()
    {
        $this->selected_rooms = [];
        $this->select_all = false;
    }

    public function updatedFilterFloorId()
    {
        $this->selected_rooms = [];
        $this->select_all = false;
    }

    protected function getRooms()
    {
        $branchId = auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id;

        return Room::where('branch_id', $branchId)
            ->when($this->filter_type_id, fn($q) => $q->where('type_id', $this->filter_type_id))
            ->when($this->filter_floor_id, fn($q) => $q->where('floor_id', $this->filter_floor_id))
            ->when($this->search, fn($q) => $q->where('number', 'like', '%' . $this->search . '%'))
            ->with(['type', 'floor', 'rates.stayingHour'])
            ->orderBy('number')
            ->get();
    }

    public function openRateModal()
    {
        if (empty($this->selected_rooms)) {
            $this->dialog()->error(
                $title = 'No Rooms Selected',
                $description = 'Please select at least one room to set rates.'
            );
            return;
        }

        $stayingHours = StayingHour::where('branch_id', $this->branch_id)->orderBy('number')->get();
        $this->rate_amounts = [];

        foreach ($stayingHours as $sh) {
            $this->rate_amounts[$sh->id] = [
                'staying_hour_id' => $sh->id,
                'hours' => $sh->number,
                'amount' => '',
            ];
        }

        $this->rate_modal = true;
    }

    public function applyRates()
    {
        $hasAmount = collect($this->rate_amounts)->filter(fn($entry) => $entry['amount'] !== '' && $entry['amount'] !== null)->isNotEmpty();

        if (!$hasAmount) {
            $this->dialog()->error(
                $title = 'No Rates Entered',
                $description = 'Please enter at least one rate amount.'
            );
            return;
        }

        $branchId = auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id;
        $rooms = Room::whereIn('id', $this->selected_rooms)->get();
        $updatedCount = 0;

        foreach ($rooms as $room) {
            foreach ($this->rate_amounts as $entry) {
                if ($entry['amount'] === '' || $entry['amount'] === null) {
                    continue;
                }

                rateModel::updateOrCreate(
                    [
                        'branch_id' => $branchId,
                        'room_id' => $room->id,
                        'staying_hour_id' => $entry['staying_hour_id'],
                    ],
                    [
                        'type_id' => $room->type_id,
                        'amount' => $entry['amount'],
                        'is_available' => true,
                    ]
                );
                $updatedCount++;
            }
        }

        ActivityLog::create([
            'branch_id' => $branchId,
            'user_id' => auth()->user()->id,
            'activity' => 'Bulk Update Rates',
            'description' => 'Updated rates for ' . count($this->selected_rooms) . ' room(s).',
        ]);

        $this->rate_modal = false;
        $this->selected_rooms = [];
        $this->select_all = false;
        $this->rate_amounts = [];

        $this->dialog()->success(
            $title = 'Rates Updated',
            $description = $updatedCount . ' rate(s) updated successfully.'
        );
    }

    public function saveStayingHour()
    {
        $this->validate([
            'number' => 'required|integer|min:1',
        ]);

        $branchId = auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id;

        $exists = StayingHour::where('branch_id', $branchId)->where('number', $this->number)->exists();
        if ($exists) {
            $this->dialog()->error(
                $title = 'Already Exists',
                $description = $this->number . ' hour staying hour already exists.'
            );
            return;
        }

        StayingHour::create([
            'branch_id' => $branchId,
            'number' => $this->number,
        ]);

        ActivityLog::create([
            'branch_id' => $branchId,
            'user_id' => auth()->user()->id,
            'activity' => 'Create Staying Hour',
            'description' => 'Created staying hour ' . $this->number . ' hours.',
        ]);

        $this->reset(['number']);
        $this->dialog()->success(
            $title = 'Staying Hour Saved',
            $description = 'Staying Hour was successfully saved.'
        );
        $this->add_staying_hour_modal = false;
    }

    public function openAddHour()
    {
        if (!$this->branch_id) {
            $this->dialog()->error(
                $title = 'No Branch',
                $description = 'Please select a branch first.'
            );
            return;
        }
        $this->add_staying_hour_modal = true;
    }

}
