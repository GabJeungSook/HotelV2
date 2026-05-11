<?php

namespace App\Http\Livewire\Frontdesk\Food;

use App\Models\ActivityLog;
use Livewire\Component;
use App\Models\FrontdeskCategory;
use App\Models\ParentCategory;
use WireUi\Traits\Actions;
use Filament\Tables;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Layout;


class Category extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;
    use Actions;
    public $name;
    public $category_id;
    public $parent_category_id;
    public $add_modal = false;
    public $edit_modal = false;
    public $branch_id;

    protected function getTableQuery(): Builder
    {
        if(auth()->user()->hasRole('superadmin'))
        {
            return FrontdeskCategory::query();
        }else{
            return FrontdeskCategory::query()->where(
                'branch_id',
                auth()->user()->branch_id
            );
        }
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('branch.name')
                ->label('BRANCH')
                ->formatStateUsing(
                    fn(string $state): string => strtoupper("{$state}")
                )
                ->sortable()
                ->visible(fn () => auth()->user()->hasRole('superadmin')),
            TextColumn::make('parentCategory.name')
                ->label('PARENT CATEGORY')
                ->searchable()
                ->sortable(),
            TextColumn::make('name')
                ->label('CATEGORY NAME')
                ->searchable()
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\EditAction::make('type.edit')
                ->icon('heroicon-o-pencil-alt')
                ->color('success')
                ->action(function ($record, $data) {
                    $record->update($data);
                    $this->dialog()->success(
                        $title = 'Category Updated',
                        $description = 'Category was successfully updated'
                    );
                })
                ->form(function ($record) {
                    return [
                        Grid::make(1)->schema([
                            Select::make('parent_category_id')
                                ->label('Parent Category')
                                ->options(ParentCategory::pluck('name', 'id'))
                                ->default($record->parent_category_id)
                                ->required(),
                            TextInput::make('name')
                                ->default($record->name)
                                ->rules(['required']),
                        ]),
                    ];
                })
                ->modalHeading('Update Category')
                ->modalWidth('lg'),
            Tables\Actions\DeleteAction::make('user.destroy'),
        ];
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

    public function saveCategory()
    {
        $this->validate([
            'name' => 'required',
            'parent_category_id' => 'required|exists:parent_categories,id',
        ]);

        FrontdeskCategory::create([
            'name' => $this->name,
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'parent_category_id' => $this->parent_category_id,
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Create Category',
            'description' => 'Created category ' . $this->name,
        ]);

        $this->name = '';
        $this->parent_category_id = null;
        $this->add_modal = false;
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Category Added Successfully'
        );
    }

    public function render()
    {
        return view('livewire.frontdesk.food.category', [
            'branches' => \App\Models\Branch::all(),
            'parentCategories' => ParentCategory::all(),
        ]);
    }
}
