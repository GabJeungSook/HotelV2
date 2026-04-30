<?php

namespace App\Http\Livewire\Admin\Manage;

use App\Models\ActivityLog;
use App\Models\CheckinDetail;
use App\Services\KioskBatchService;
use Livewire\Component;
use App\Models\Room as roomModel;
use App\Models\Type;
use App\Models\Floor;
use WireUi\Traits\Actions;
use Livewire\WithPagination;
use Filament\Tables;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Layout;

class Room extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;
    use Actions;
    public $add_modal = false;
    public $edit_modal = false;
    public $types;
    public $number, $floor, $type, $status, $room_id;
    public $floors;
    public $area;
    public $filter_status, $filter_floor;
    public $search;
    public $branch_id;

    public function mount()
    {

        $this->types = Type::where(
            'branch_id',
            auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id
        )->get();

        $this->floors = Floor::where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
            ->orderBy('number', 'asc')
            ->get();
    }

    public function updatedBranchId()
    {
        $this->types = Type::where(
            'branch_id',
            $this->branch_id
        )->get();

        $this->floors = Floor::where('branch_id', $this->branch_id)
            ->orderBy('number', 'asc')
            ->get();
    }

    protected function getTableQuery(): Builder
    {
        if(auth()->user()->hasRole('superadmin')){
            return roomModel::query()
            ->with(['type', 'floor', 'branch'])
            ->orderBy('branch_id', 'asc')
            ->orderBy('number', 'asc');
        }else{
            return roomModel::query()
            ->with(['type', 'floor', 'branch'])
            ->where('branch_id', auth()->user()->branch_id)
            ->orderBy('number', 'asc');
        }
    }

    public function render()
    {
        return view('livewire.admin.manage.room',
        [
            'branches' => \App\Models\Branch::all(),
        ]);
    }

    protected function getTableColumns(): array
    {
        return [
             Tables\Columns\TextColumn::make('branch.name')
                ->label('BRANCH')
                ->formatStateUsing(
                    fn(string $state): string => strtoupper("{$state}")
                )
                ->sortable()
                ->visible(fn () => auth()->user()->hasRole('superadmin')),
                Tables\Columns\TextColumn::make('number')
                    ->formatStateUsing(
                        fn (string $state): string => __("ROOM #{$state}")
                    )
                    ->label('NUMBER')
                    ->sortable()
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($q) use ($search) {
                            // Normalize search input
                            $cleanSearch = strtolower($search);
                            $cleanSearch = str_replace(['room', '#'], '', $cleanSearch);
                            $cleanSearch = trim($cleanSearch);

                            // Allow search by raw number
                            if (is_numeric($cleanSearch)) {
                                $q->orWhere('number', $cleanSearch);
                            }

                            // Allow search by "ROOM 101" style text
                            $q->orWhere('number', 'like', "%{$cleanSearch}%");
                        });
                    }),
            Tables\Columns\TextColumn::make('type.name')
                ->formatStateUsing(function (string $state) {
                    return strtoupper($state);
                })
                ->label('TYPE')
                ->searchable(),
            Tables\Columns\TextColumn::make('status')
                ->label('TYPE')
                ->extraAttributes(function ($record) {
                    switch ($record->status) {
                        case 'Available':
                            return [
                                'class' => '!text-white !bg-green-700 opacity-75',
                            ];
                            break;
                        case 'Occupied':
                            return [
                                'class' => '!text-green !bg-green-200 opacity-75',
                            ];
                            break;
                        case 'Reserved':
                            return [
                                'class' => '!text-gray-800 !bg-gray-400 opacity-75',
                            ];
                            break;
                        case 'Maintenance':
                            return [
                                'class' => '!text-indigo-800 !bg-indigo-400 opacity-75',
                            ];
                        case 'Uncleaned':
                            return [
                                'class' => '!text-red-800 !bg-red-400 opacity-75',
                            ];
                            break;
                        case 'Cleaning':
                            return [
                                'class' => '!text-red-800 !bg-red-400 opacity-75',
                            ];
                            break;
                        case 'Cleaned':
                            return [
                                'class' => '!text-blue-800 !bg-blue-400 opacity-75',
                            ];
                            break;

                        default:
                            # code...
                            break;
                    }
                }),
            Tables\Columns\TextColumn::make('floor.number')
                ->formatStateUsing(function (string $state) {
                    $ends = [
                        'th',
                        'st',
                        'nd',
                        'rd',
                        'th',
                        'th',
                        'th',
                        'th',
                        'th',
                        'th',
                    ];
                    if ($state % 100 >= 11 && $state % 100 <= 13) {
                        return $state . 'th' . ' Floor';
                    } else {
                        return $state . $ends[$state % 10] . ' Floor';
                    }
                })

                ->label('FLOOR')
                ->searchable(),
            Tables\Columns\TextColumn::make('area')
            ->label('Area')
            ->sortable()
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\EditAction::make('type.edit')
                ->icon('heroicon-o-pencil-alt')
                ->color('success')
                ->action(function ($record, $data) {
                    $newStatus = $data['status'] ?? $record->status;
                    $oldStatus = $record->status;

                    // Prevent changing to Available if room has an active guest
                    if ($newStatus === 'Available') {
                        $hasActiveGuest = CheckinDetail::where('room_id', $record->id)
                            ->where('is_check_out', false)
                            ->exists();

                        if ($hasActiveGuest) {
                            $this->dialog()->error(
                                $title = 'Cannot Set to Available',
                                $description = 'This room has an active guest checked in. Please check out the guest first before setting to Available.'
                            );
                            return;
                        }
                    }

                    // Prevent manually setting room to Occupied - should only happen via check-in
                    if ($newStatus === 'Occupied' && $oldStatus !== 'Occupied') {
                        $this->dialog()->error(
                            $title = 'Cannot Set to Occupied',
                            $description = 'Room status can only be set to Occupied through the check-in process. Please use the Check-In function instead.'
                        );
                        return;
                    }

                    // Prevent changing to Maintenance if room has ongoing cleaning
                    if ($newStatus === 'Maintenance') {
                        $hasOngoingCleaning = $record->status === 'Cleaning' || $record->cleaning_by_user_id !== null;

                        if ($hasOngoingCleaning) {
                            $this->dialog()->error(
                                $title = 'Cannot Set to Maintenance',
                                $description = 'This room has ongoing cleaning. Please finish cleaning first before setting to Maintenance.'
                            );
                            return;
                        }

                        // Prevent changing to Maintenance if room has active guest
                        $hasActiveGuest = CheckinDetail::where('room_id', $record->id)
                            ->where('is_check_out', false)
                            ->exists();

                        if ($hasActiveGuest) {
                            $this->dialog()->error(
                                $title = 'Cannot Set to Maintenance',
                                $description = 'This room has an active guest checked in. Please transfer or check out the guest first before setting to Maintenance.'
                            );
                            return;
                        }
                    }

                    $record->update($data);

                    // If the admin moved the room into a kiosk-eligible state,
                    // notify the kiosk batch so the room becomes visible
                    // without waiting for the next roomboy cleaning cycle.
                    // maybeFillBlankFloor only inserts a slot if the floor
                    // currently has none for that type — safe to call always.
                    $record->refresh();
                    if (in_array($record->status, ['Available', 'Cleaned'], true)
                        && $record->is_priority) {
                        KioskBatchService::maybeFillBlankFloor($record);
                    }

                    ActivityLog::create([
                        'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                        'user_id' => auth()->user()->id,
                        'activity' => 'Update Room',
                        'description' => 'Updated room ' . $record->number,
                    ]);

                    $this->dialog()->success(
                        $title = 'Room Updated',
                        $description = 'The room has been updated successfully.'
                    );
                })
                ->form(function ($record) {
                    return [
                        Grid::make(2)->schema([
                            TextInput::make('number')
                                ->default($record->number)
                                ->required(),
                            Select::make('type_id')
                            ->disablePlaceholderSelection()
                                ->label('Type')
                                ->options(
                                    Type::where(
                                        'branch_id',
                                        $record->branch_id
                                    )->pluck('name', 'id')
                                )
                                ->default($record->id),
                            Select::make('floor_id')
                             ->disablePlaceholderSelection()
                                ->label('Floor')
                                ->options(
                                    Floor::where(
                                        'branch_id',
                                        $record->branch_id
                                    )->pluck('number', 'id')
                                )
                                ->default($record->id),
                            Select::make('status')
                             ->disablePlaceholderSelection()
                                ->options([
                                    'Available' => 'Available',
                                    'Occupied' => 'Occupied',
                                    'Reserved' => 'Reserved',
                                    'Maintenance' => 'Maintenance',
                                    'Uncleaned' => 'Uncleaned',
                                    'Cleaning' => 'Cleaning',
                                    'Cleaned' => 'Cleaned',
                                ])
                                ->default($record->status),
                            Select::make('area')
                             ->disablePlaceholderSelection()
                                ->options([
                                    'Main' => 'Main',
                                    'Extension' => 'Extension',
                                ])
                                ->default($record->area),
                        ]),
                    ];
                })
                ->modalHeading('Update Room')
                ->modalWidth('xl'),
        ];
    }

    protected function getTableFilters(): array
    {
        if(auth()->user()->hasRole('superadmin')){
            return [
                SelectFilter::make('branch')->relationship('branch', 'name'),
                SelectFilter::make('Status')
                ->label('Select Status')
                ->options([
                    'Available' => 'Available',
                    'Occupied' => 'Occupied',
                    'Reserved' => 'Reserved',
                    'Maintenance' => 'Maintenance',
                    'Uncleaned' => 'Uncleaned',
                    'Cleaning' => 'Cleaning',
                    'Cleaned' => 'Cleaned',
                ]),
            SelectFilter::make('floor_id')
                ->label('Select Floor')
                ->options(
                    Floor::where('branch_id', auth()->user()->branch_id)
                        ->pluck('number', 'id')
                        ->map(function ($query) {
                            $ends = [
                                'th',
                                'st',
                                'nd',
                                'rd',
                                'th',
                                'th',
                                'th',
                                'th',
                                'th',
                                'th',
                            ];
                            if ($query % 100 >= 11 && $query % 100 <= 13) {
                                return $query . 'th' . ' Floor';
                            } else {
                                return $query . $ends[$query % 10] . ' Floor';
                            }
                        })
                ),
            ];
        }else{
        return [
            SelectFilter::make('type_id')
                ->label('Select Type')
                ->options(
                    Type::where('branch_id', auth()->user()->branch_id)
                        ->pluck('name', 'id')
                ),
            SelectFilter::make('Status')
                ->label('Select Status')
                ->options([
                    'Available' => 'Available',
                    'Occupied' => 'Occupied',
                    'Reserved' => 'Reserved',
                    'Maintenance' => 'Maintenance',
                    'Uncleaned' => 'Uncleaned',
                    'Cleaning' => 'Cleaning',
                    'Cleaned' => 'Cleaned',
                ]),
            SelectFilter::make('floor_id')
                ->label('Select Floor')
                ->options(
                    Floor::where('branch_id', auth()->user()->branch_id)
                        ->pluck('number', 'id')
                        ->map(function ($query) {
                            $ends = [
                                'th',
                                'st',
                                'nd',
                                'rd',
                                'th',
                                'th',
                                'th',
                                'th',
                                'th',
                                'th',
                            ];
                            if ($query % 100 >= 11 && $query % 100 <= 13) {
                                return $query . 'th' . ' Floor';
                            } else {
                                return $query . $ends[$query % 10] . ' Floor';
                            }
                        })
                ),
        ];
        }

    }

     protected function getTableFiltersLayout(): ?string
    {
        return Layout::AboveContent;
    }

    public function clearFilter()
    {
        $this->filter_status = null;
        $this->filter_floor = null;
    }

    public function saveRoom()
    {
        $this->validate([
            'number' => 'required|string|unique:rooms,number,NULL,id,branch_id,' . (auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id),
            'type' => 'required',
            'floor' => 'required',
            'status' => 'required',
        ],
        [
            'number.required' => 'The room number is required.',
            'number.unique' => 'The room number has already been taken.',
            'type.required' => 'The room type is required.',
            'floor.required' => 'The floor is required.',
            'status.required' => 'The status is required.',
        ]);

        roomModel::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'number' => $this->number,
            'type_id' => $this->type,
            'floor_id' => $this->floor,
            'status' => $this->status,
            'area' => $this->area,
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Create Room',
            'description' => 'Created room ' . $this->number,
        ]);

        $this->dialog()->success(
            $title = 'Room saved',
            $description = 'The room has been saved successfully.'
        );

        $this->add_modal = false;
        $this->reset(['number', 'type', 'floor', 'status', 'area']);
    }

    public function editRoom($room_id)
    {
        $room = roomModel::where('id', $room_id)->where('branch_id', $this->branch_id)->first();
        $this->room_id = $room->id;
        $this->number = $room->number;
        $this->type = $room->type_id;
        $this->floor = $room->floor_id;
        $this->area = $room->area;
        $this->status = $room->status;
        $this->edit_modal = true;
    }

    public function updateRoom()
    {
        $this->validate([
            'number' => 'required|string|unique:rooms,number,NULL,id,branch_id,' . (auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id),
            'type' =>
                'required|exists:types,id,branch_id,' .
                auth()->user()->branch_id,
            'floor' =>
                'required|exists:floors,id,branch_id,' .
                auth()->user()->branch_id,
        ]);

        $room = roomModel::where('id', $this->room_id)->where('branch_id', $this->branch_id)->first();
        $room->update([
            'number' => $this->number,
            'type_id' => $this->type,
            'floor_id' => $this->floor,
            'status' => $this->status,
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Update Room',
            'description' => 'Updated room ' . $this->number,
        ]);

        $this->dialog()->success(
            $title = 'Room Updated',
            $description = 'The room has been updated successfully.'
        );
        $this->reset(['number', 'type', 'floor', 'status', 'room_id']);

        $this->edit_modal = false;
    }
}
