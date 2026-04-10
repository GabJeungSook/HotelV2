<?php

namespace App\Http\Livewire\Admin\Manage;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Room;
use App\Models\Type;
use Filament\Tables;
use Livewire\Component;
use WireUi\Traits\Actions;
use App\Models\StayingHour;
use Livewire\WithPagination;
use App\Models\Rate as rateModel;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Illuminate\Contracts\View\View;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

class Rate extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;
    use Actions;
    public $add_modal = false;
    public $add_staying_hour_modal = false;
    public $edit_modal = false;
    public $amount, $hours_id, $type_id, $room_id, $rate_id;
    public $search;
    public $has_discount = false;
    public $branch_id;
    public $branch_name;
    public $number;

    public function render()
    {
        return view('livewire.admin.manage.rate', [
            // 'stayingHours' => StayingHour::where(
            //     'branch_id',
            //     auth()->user()->branch_id
            // )->get(),
            'stayingHours' => StayingHour::where('branch_id',$this->branch_id)->get(),
            'branches' => Branch::all(),
        ]);
    }

    public function mount()
    {
        if(auth()->user()->hasRole('admin'))
        {
                $this->branch_id = auth()->user()->branch_id;
                $this->branch_name = Branch::where('id', $this->branch_id)->first()->name;
        }
    }
    protected function getTableQuery(): Builder
    {
        if(auth()->user()->hasRole('superadmin'))
        {
            return Room::query()
            ->where('branch_id', $this->branch_id)
            ->with(['rates.stayingHour', 'type']);
        }else{
            return Room::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->with(['rates.stayingHour', 'type']);
        }
    }

    public function updatedBranchId()
    {
        $this->branch_name = Branch::where('id', $this->branch_id)->value('name');
        $this->resetPage();
    }

    public function getTableContent()
    {
        return view('custom-table', [
            'types' => Room::query()
                ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
                ->with(['type', 'rates.stayingHour'])
                ->get(),
        ]);
    }

    public function toggleDiscount($id)
    {
        $this->dialog()->confirm([
            'title'       => 'Are you Sure?',
            'description' => 'Do you want to update the discount status of this rate?',
            'acceptLabel' => 'Yes, update it',
            'method'      => 'saveDiscount',
            'params'      => $id,
        ]);
    }

    public function saveDiscount($id)
    {
        $rate = rateModel::find($id);
        $rate->has_discount = !$rate->has_discount;
        $rate->save();

        $this->dialog()->success(
            $title = 'Discount Updated',
            $description = 'Discount status has been updated successfully.'
        );
    }


    protected function getTableColumns(): array
    {
        return [
            // Tables\Columns\TextColumn::make('rates.stayingHour.number')
            //     ->label('HOURS')
            //     ->searchable()
            //     ->sortable(),
            // Tables\Columns\TextColumn::make('rates.amount')
            //     ->label('AMOUNT')
            //     ->searchable()
            //     ->sortable(),
        ];
    }

    public function saveRate()
    {
        $this->validate([
            'amount' => 'required|regex:/^\d+$/',
            'hours_id' => 'required',
            'room_id' => 'required',
        ]);

         $room = Room::find($this->room_id);

         rateModel::create([
                'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                'amount' => $this->amount,
                'staying_hour_id' => $this->hours_id,
                'type_id' => $room?->type_id,
                'room_id' => $this->room_id,
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Create Rate',
                'description' => 'Created rate ' . $this->amount . ' for room ID ' . $this->room_id,
            ]);

            $this->reset(['amount', 'hours_id', 'type_id', 'room_id']);
            $this->dialog()->success(
                $title = 'Rate Saved',
                $description = 'Rate was successfully saved'
            );
            $this->add_modal = false;

        // $rate_exists = rateModel::where('staying_hour_id', $this->hours_id)
        //     ->where('type_id', $this->type_id)
        //     ->where('amount', $this->amount)
        //     ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
        //     ->exists();

        // if ($rate_exists) {
        //     $this->dialog()->error(
        //         $title = 'Rate Exists',
        //         $description = 'The rate you are trying to add already exists.'
        //     );
        // } elseif (
        //     rateModel::where('staying_hour_id', $this->hours_id)
        //         ->where('amount', $this->amount)
        //         ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
        //         ->exists()
        // ) {
        //     $this->dialog()->error(
        //         $title = 'Rate Exists',
        //         $description = 'The rate you are trying to add already exists.'
        //     );
        // } elseif (
        //     rateModel::where('type_id', $this->type_id)
        //         ->where('amount', $this->amount)
        //         ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
        //         ->exists()
        // ) {
        //     $this->dialog()->error(
        //         $title = 'Rate Exists',
        //         $description = 'The rate you are trying to add already exists.'
        //     );
        // } elseif (
        //     rateModel::where('staying_hour_id', $this->hours_id)
        //         ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
        //         ->where('type_id', $this->type_id)
        //         ->exists()
        // ) {
        //     $this->dialog()->error(
        //         $title = 'Rate Exists',
        //         $description = 'The rate you are trying to add already exists.'
        //     );
        // } else {
        //     rateModel::create([
        //         'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
        //         'amount' => $this->amount,
        //         'staying_hour_id' => $this->hours_id,
        //         'type_id' => $this->type_id,
        //     ]);

        //     ActivityLog::create([
        //         'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
        //         'user_id' => auth()->user()->id,
        //         'activity' => 'Create Rate',
        //         'description' => 'Created rate ' . $this->amount . ' for type ID ' . $this->type_id,
        //     ]);

        //     $this->reset(['amount', 'hours_id', 'type_id']);
        //     $this->dialog()->success(
        //         $title = 'Rate Saved',
        //         $description = 'Rate was successfully saved'
        //     );
        //     $this->add_modal = false;
        // }
    }

    public function saveStayingHour()
    {
        $this->validate([
            'number' => 'required|integer',
        ]);

        StayingHour::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'number' => $this->number
        ]);

         ActivityLog::create([
                'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Create Staying Hour',
                'description' => 'Created staying hour ' . $this->number . ' hours.',
            ]);

            $this->reset(['number']);
            $this->dialog()->success(
                $title = 'Staying Hour Saved',
                $description = 'Staying Hour was successfully saved'
            );
            $this->add_staying_hour_modal = false;
    }

    public function editRate($rate_id)
    {
        $rate = rateModel::where('id', $rate_id)->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)->first();
        $this->amount = $rate->amount;
        $this->hours_id = $rate->staying_hour_id;
        $this->type_id = $rate->type_id;
        $this->room_id = $rate->room_id;
        $this->rate_id = $rate->id;
        $this->edit_modal = true;
    }

    public function openAdd()
    {
        $branch = Branch::find($this->branch_id);
        if($branch->types->isEmpty())
        {
            $this->dialog()->error(
                $title = 'No Types Found',
                $description = 'Please add a room type first before proceeding to add a new rate.'
            );
        }else{
            $this->add_modal = true;
        }
    }

    public function openAddHour()
    {
        $branch = Branch::find($this->branch_id);
        if($branch->types->isEmpty())
        {
            $this->dialog()->error(
                $title = 'No Types Found',
                $description = 'Please add a room type first before proceeding to add a new staying hour.'
            );
        }else{
            $this->add_staying_hour_modal = true;
        }
    }

    public function updateRates()
    {
        $this->validate([
            'amount' => 'required|regex:/^\d+$/',
            'hours_id' => 'required',
            'room_id' => 'required',
        ]);

        $rate_exists = rateModel::where('staying_hour_id', $this->hours_id)
            ->where('room_id', $this->room_id)
            ->where('amount', $this->amount)
            ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
            ->where('id', '!=', $this->rate_id)
            ->exists();

        if ($rate_exists) {
            $this->dialog()->error(
                $title = 'Rate Exists',
                $description = 'The rate you are trying to add already exists.'
            );
        } elseif (
            rateModel::where('staying_hour_id', $this->hours_id)
                ->where('amount', $this->amount)
                ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
                ->where('id', '!=', $this->rate_id)
                ->exists()
        ) {
            $this->dialog()->error(
                $title = 'Rate Exists',
                $description = 'The rate you are trying to add already exists.'
            );
        } elseif (
            rateModel::where('room_id', $this->room_id)
                ->where('amount', $this->amount)
                ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
                ->where('id', '!=', $this->rate_id)
                ->exists()
        ) {
            $this->dialog()->error(
                $title = 'Rate Exists',
                $description = 'The rate you are trying to add already exists.'
            );
        } elseif (
            rateModel::where('staying_hour_id', $this->hours_id)
                ->where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
                ->where('room_id', $this->room_id)
                ->where('id', '!=', $this->rate_id)
                ->exists()
        ) {
            $this->dialog()->error(
                $title = 'Rate Exists',
                $description = 'The rate you are trying to add already exists.'
            );
        } else {
            rateModel::where('id', $this->rate_id)->update([
                'amount' => $this->amount,
                'staying_hour_id' => $this->hours_id,
                'type_id' => Room::find($this->room_id)?->type_id,
                'room_id' => $this->room_id,
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Update Rate',
                'description' => 'Updated rate ' . $this->amount . ' for room ID ' . $this->room_id,
            ]);

            $this->reset(['amount', 'hours_id', 'type_id', 'room_id']);
            $this->dialog()->success(
                $title = 'Rate Saved',
                $description = 'Rate was successfully saved'
            );
            $this->edit_modal = false;
        }
    }
}
