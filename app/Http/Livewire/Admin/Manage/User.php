<?php

namespace App\Http\Livewire\Admin\Manage;

use App\Models\ActivityLog;
use Livewire\Component;
use App\Models\User as userModel;
use Livewire\WithPagination;
use WireUi\Traits\Actions;
use Filament\Tables;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Layout;

class User extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;
    use Actions;

    public $type = 1;
    public $add_modal = false;
    public $edit_modal = false;
    public $search;
    public $name, $email, $password, $role, $user_id;
    public $branch_id = 1;

    public function render()
    {
        return view('livewire.admin.manage.user', [
            'users' => userModel::where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)
                ->whereHas('roles', function ($role) {
                    $role->where('name', '!=', 'superadmin');
                })
                ->with('roles')
                ->paginate(10),
            'branches' => \App\Models\Branch::all(),
        ]);
    }         

private function isUserOnline($user, $threshold)
{
    return $user->sessions()
        ->where('last_activity', '>=', $threshold)
        ->exists();
}


    protected function getTableQuery(): Builder
    {
        if(auth()->user()->hasRole('superadmin')){
            return userModel::query()
            ->whereHas('roles', function ($role) {
                $role->whereNotIn('name', ['superadmin']);
            })
            ->with('roles');
        }else{
            return userModel::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->whereHas('roles', function ($role) {
                $role->whereNotIn('name', ['superadmin']);
            })
            ->with('roles');
        }
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
            Tables\Columns\TextColumn::make('name')
                ->label('NAME')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('email')
                ->label('EMAIL')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('roles.name')
                ->formatStateUsing(function ($record) {
                    if($record->roles->first() != null)
                    {
                        return strtoupper(str_replace('_', ' ', $record->roles->first()->name));
                    }else{
                        return 'NO ROLE';
                    }

                })
                // ->formatStateUsing(fn (string $state): string => strtoupper($state))
                ->label('ROLES')
                ->searchable()
                ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('ACTIVE STATUS')
                    ->onColor('success')
                    ->offColor('danger')
                    ->action(function ($record) {
                        $record->update([
                            'is_active' => !$record->is_active
                        ]);
                    })->disabled(fn ($record) => !auth()->user()->hasRole('superadmin') && $record->hasRole('admin')),
                    Tables\Columns\BadgeColumn::make('online')
                        ->label('LOGIN STATUS')
                        ->getStateUsing(function ($record) {
                            // Consider “online” if there’s activity in the last 5 minutes
                            $threshold = now()->subMinutes(5)->timestamp;

                            return $record->sessions()
                                ->where('last_activity', '>=', $threshold)
                                ->exists() ? 'yes' : 'no';
                        })
                        ->formatStateUsing(fn ($state) => $state === 'yes' ? 'Online' : 'Offline')
                         ->icon(static function ($state): string {
                            if ($state === 'yes') {
                                return 'heroicon-o-link';
                            }else{
                                return 'heroicon-o-x-circle';
                            }
                        })
                        ->enum([
                            'yes' => 'Online',
                            'no' => 'Offline',
                        ])
                        ->colors([
                            'success' => 'yes',
                            'danger'  => 'no',
                        ]),
        ];
    }

    protected function getTablePollingInterval(): ?string
    {
        return '5s';
    }

     protected function getTableFilters(): array
    {
        if(auth()->user()->hasRole('superadmin')){
            return [
                SelectFilter::make('branch')->relationship('branch', 'name')
            ];
        }else{
            return [];
        }
    }

    protected function getTableFiltersLayout(): ?string
    {
        return Layout::AboveContent;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\EditAction::make('user.edit')
                ->icon('heroicon-o-pencil-alt')
                ->color('success')
                ->action(function ($record, $data) {
                    if ($data['role'] != $record->getRoleNames()->first()) {
                        //if recoed has no role, do not remove role
                        if($record->roles->first() != null)
                        {
                             $record->removeRole($record->roles->first()->name);
                        }
                        $record->update([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => bcrypt($data['password']),
                            'role' => $data['role'],
                        ]);
                        $record->assignRole($data['role']);

                        ActivityLog::create([
                            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                            'user_id' => auth()->user()->id,
                            'activity' => 'Update User',
                            'description' => 'Updated user ' . $data['name'],
                        ]);

                        $this->dialog()->success(
                            $title = 'User Updated',
                            $description =
                                'The user has been updated successfully.'
                        );
                    } else {
                        $record->update([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => bcrypt($data['password']),
                            'role' => $data['role'],
                        ]);

                        ActivityLog::create([
                            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                            'user_id' => auth()->user()->id,
                            'activity' => 'Update User',
                            'description' => 'Updated user ' . $data['name'],
                        ]);

                        $this->dialog()->success(
                            $title = 'User Updated',
                            $description =
                                'The user has been updated successfully.'
                        );
                    }
                })
                ->form(function ($record) {
                    return [
                        Grid::make(2)->schema([
                            TextInput::make('name')->default($record->name)->required(),
                            TextInput::make('email')->default($record->email)->required(),
                            TextInput::make('password')
                                ->password()
                                ->default($record->password)
                                ->required(),
                            Select::make('role')
                                ->options([
                                    'admin' => 'Admin',
                                    'supervisor' => 'Supervisor',
                                    'frontdesk' => 'Frontdesk',
                                    'kiosk' => 'Kiosk',
                                    'kitchen' => 'Kitchen',
                                    'roomboy' => 'Roomboy',
                                    'back_office' => 'Back Office',
                                ])->required()
                                ->default( $record->getRoleNames()->first() ?? null),
                        ]),
                    ];
                })
                ->modalHeading('Update User')
                ->modalWidth('xl'),
            // Tables\Actions\DeleteAction::make('user.destroy')->visible(fn ($record) => !$record->hasRole('admin'))->action(function (
            //     $record
            // ) {
            //     if($record->roles->first() != null)
            //     {
            //         $record->removeRole($record->roles->first()->name);
            //     }
            //     $record->delete();
            //     $this->dialog()->success(
            //         $title = 'User Deleted',
            //         $description = 'The user has been deleted successfully.'
            //     );
            // }),
        ];
    }

    public function saveUser()
    {
        $this->validate([
            'name' => 'required|unique:users,name,',
            'email' => 'required|email|unique:users,email,',
            'password' => 'required',
            'role' => 'required',
        ]);

        $user = userModel::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt($this->password),
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'branch_name' => auth()->user()->hasRole('superadmin') ? \App\Models\Branch::where('id', $this->branch_id)->first()->name : auth()->user()->branch->name,
        ]);

        $user->assignRole($this->role);

        if ($this->role == 'frontdesk') {
            \App\Models\Frontdesk::create([
                'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                'user_id' => $user->id,
                'name' => $this->name,
                'number' => '+639000000000',
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Create Frontdesk',
                'description' => 'Created frontdesk ' . $this->name,
            ]);
        }

        ActivityLog::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Create User',
            'description' => 'Created user ' . $this->name,
        ]);

        $this->dialog()->success(
            $title = 'User Saved',
            $description = 'The user has been saved successfully.'
        );
        $this->add_modal = false;

        $this->reset(['name', 'email', 'password', 'role']);
    }

    public function editUser($user_id)
    {
        $user = userModel::where('id', $user_id)->first();

        $this->user_id = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->getRoleNames()[0];

        $this->edit_modal = true;
    }

    public function updateUser()
    {
        $user = userModel::where('id', $this->user_id)->first();

        $this->validate([
            'name' => 'required|unique:users,name,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'required',
            'role' => 'required',
        ]);

        if ($this->role != $user->roles->first()->name) {
            $user->removeRole($user->roles->first()->name);
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
                'password' => bcrypt($this->password),
                'role' => $this->role,
            ]);
            $user->assignRole($this->role);

            ActivityLog::create([
                'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Update User',
                'description' => 'Updated user ' . $this->name,
            ]);

            $this->dialog()->success(
                $title = 'User Updated',
                $description = 'The user has been updated successfully.'
            );
            $this->edit_modal = false;
            $this->reset(['name', 'email', 'password', 'role']);
        } else {
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
                'password' => bcrypt($this->password),
                'role' => $this->role,
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Update User',
                'description' => 'Updated user ' . $this->name,
            ]);

            $this->dialog()->success(
                $title = 'User Updated',
                $description = 'The user has been updated successfully.'
            );
            $this->edit_modal = false;
            $this->reset(['name', 'email', 'password', 'role']);
        }
    }
    public function deleteUser($user_id)
    {
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'delete this user?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, delete this user',
                'method' => 'confirmDelete',
                'params' => [$user_id],
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function confirmDelete($user_id)
    {
        $user = userModel::where('id', $user_id)->first();
        $user->removeRole($user->roles->first()->name);
        $user->delete();

        $this->dialog()->success(
            $title = 'User Deleted',
            $description = 'The user has been deleted successfully.'
        );
    }
}
