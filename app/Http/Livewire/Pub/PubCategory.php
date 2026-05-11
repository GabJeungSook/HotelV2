<?php

namespace App\Http\Livewire\Pub;

use Livewire\Component;
use App\Models\PubCategory as categoryModel;
use App\Models\ParentCategory;
use WireUi\Traits\Actions;
use Filament\Tables;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class PubCategory extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;
    use Actions;
    public $name;
    public $category_id;
    public $parent_category_id;
    public $add_modal = false;
    public $edit_modal = false;

    protected function getTableQuery(): Builder
    {
        return categoryModel::query()->where(
            'branch_id',
            auth()->user()->branch_id
        );
    }

    public function render()
    {
        return view('livewire.pub.pub-category', [
            'parentCategories' => ParentCategory::all(),
        ]);
    }

    protected function getTableColumns(): array
    {
        return [
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
                        $title = 'Type Updated',
                        $description = 'Type was successfully updated'
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

    public function saveCategory()
    {
        $this->validate([
            'name' => 'required',
            'parent_category_id' => 'required|exists:parent_categories,id',
        ]);

        categoryModel::create([
            'name' => $this->name,
            'branch_id' => auth()->user()->branch_id,
            'parent_category_id' => $this->parent_category_id,
        ]);
        $this->add_modal = false;
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Category Added Successfully'
        );
        $this->reset('name', 'parent_category_id');
    }

    public function editItem($item_id)
    {
        $item = categoryModel::where('id', $item_id)->first();
        $this->name = $item->name;
        $this->category_id = $item->id;
        $this->parent_category_id = $item->parent_category_id;

        $this->edit_modal = true;
    }

    public function updateCategory()
    {
        $this->validate([
            'name' => 'required',
            'parent_category_id' => 'required|exists:parent_categories,id',
        ]);

        categoryModel::where('id', $this->category_id)->update([
            'name' => $this->name,
            'parent_category_id' => $this->parent_category_id,
        ]);
        $this->edit_modal = false;
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Category Updated Successfully'
        );
    }

    public function deleteItem($item_id)
    {
        categoryModel::where('id', $item_id)->delete();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Category Deleted Successfully'
        );
    }
}
