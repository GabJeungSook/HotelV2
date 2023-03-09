<?php

namespace App\Http\Livewire\Kitchen;

use Livewire\Component;
use App\Models\MenuCategory;
use App\Models\Menu as menuModel;
use App\Models\Inventory;
use DB;
use WireUi\Traits\Actions;
use Filament\Tables;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;

class Menu extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;
    use Actions;
    public $add_modal = false;
    public $edit_modal = false;
    public $menu_id;
    public $name, $price, $category_id, $stock, $default_serving;

    protected function getTableQuery(): Builder
    {
        return menuModel::query()->where(
            'branch_id',
            auth()->user()->branch_id
        );
    }

    public function render()
    {
        return view('livewire.kitchen.menu', [
            'categories' => MenuCategory::where(
                'branch_id',
                auth()->user()->branch_id
            )->get(),
        ]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('NAME')
                ->formatStateUsing(function ($record) {
                    return strtoupper($record->name);
                })
                ->weight('bold')
                ->searchable()
                ->sortable(),
            TextColumn::make('price')
                ->label('PRICE')
                ->formatStateUsing(function ($record) {
                    return '₱' . number_format($record->price, 2);
                })
                ->searchable()
                ->sortable(),
            TextColumn::make('menuCategory.name')
                ->label('CATEGORY')
                ->searchable()
                ->sortable(),
            TextColumn::make('inventory.number_of_serving')
                ->label('NO. OF SERVING')
                ->searchable()
                ->sortable(),
        ];
    }

    public function saveMenu()
    {
        $this->validate([
            'name' => 'required',
            'price' => 'required',
            'category_id' => 'required',
            'stock' => 'required',
        ]);

        DB::beginTransaction();
        $menu = menuModel::create([
            'branch_id' => auth()->user()->branch_id,
            'name' => $this->name,
            'price' => $this->price,
            'menu_category_id' => $this->category_id,
        ]);

        Inventory::create([
            'branch_id' => auth()->user()->branch_id,
            'menu_id' => $menu->id,
            'number_of_serving' => $this->stock,
        ]);
        DB::commit();

        $this->add_modal = false;
        $this->reset(
            'name',
            'price',
            'category_id',
            'stock',
            'default_serving'
        );

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Menu has been added'
        );
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
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->default($record->name)
                                ->rules(['required']),
                            TextInput::make('price')
                                ->default($record->price)
                                ->numeric()
                                ->rules(['required']),
                        ]),
                    ];
                })
                ->modalHeading('Update Category')
                ->modalWidth('xl'),
            Tables\Actions\DeleteAction::make('user.destroy'),
        ];
    }

    public function editItem($menu_id)
    {
        $menu = menuModel::where('id', $menu_id)->first();
        $this->menu_id = $menu->id;
        $this->name = $menu->name;
        $this->price = $menu->price;
        $this->category_id = $menu->menu_category_id;
        $this->stock = $menu->inventory->stock;
        $this->default_serving = $menu->inventory->default_serving;
        $this->edit_modal = true;
    }

    public function updateMenu()
    {
        $menu = menuModel::where('id', $this->menu_id)->first();
        $menu->update([
            'name' => $this->name,
            'price' => $this->price,
            'menu_category_id' => $this->category_id,
        ]);

        $menu->inventory->update([
            'stock' => $this->stock,
            'default_serving' => $this->default_serving,
            'number_of_serving' => $this->stock / $this->default_serving,
        ]);

        $this->edit_modal = false;
        $this->reset(
            'name',
            'price',
            'category_id',
            'stock',
            'default_serving'
        );
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Menu has been Updated'
        );
    }

    public function deleteMenu($menu_id)
    {
        $menu = menuModel::where('id', $menu_id)->first();
        $menu->delete();

        $menu->inventory->delete();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Menu has been deleted'
        );
    }
}
