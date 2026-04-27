<?php

namespace App\Http\Livewire\Frontdesk\Food;

use App\Models\ActivityLog;
use App\Models\FrontdeskMenu;
use Livewire\Component;
use App\Models\FrontdeskInventory as InventoryModel;
use WireUi\Traits\Actions;
use App\Models\FrontdeskCategory;

class Inventory extends Component
{
    public $category;
    public $selectedItem;
    public $quantities = [];
    public $add_stock_modal = false;
    public $menu_item;
    public $menu_name;
    public $menu_price;
    public $menu_quantity;

    public $record;

    use Actions;

    public function mount($record)
    {
        $this->record = FrontdeskCategory::find($record);
    }

    public function addStock($id)
    {
        $this->add_stock_modal = true;

        $this->menu_item = FrontdeskMenu::where('id', $id)->first();
        $this->menu_name = $this->menu_item->name;
        $this->menu_price = '₱ '.number_format($this->menu_item->price, 2);
    }

    public function saveStock()
    {
        $this->validate([
            'menu_quantity' => 'required|numeric|min:1'
        ]);

        if($this->menu_item->inventory === null)
        {
            InventoryModel::create([
                'branch_id' =>  auth()->user()->branch_id,
                'frontdesk_menu_id' => $this->menu_item->id,
                'number_of_serving' => $this->menu_quantity
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Add Inventory',
                'description' => 'Added inventory for menu ' . $this->menu_item->name,
            ]);

        }else{
            $this->menu_item->inventory->update([
                'number_of_serving' => $this->menu_item->inventory->number_of_serving + $this->menu_quantity,
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Add Inventory',
                'description' => 'Added inventory for menu ' . $this->menu_item->name,
            ]);
        }



        $this->add_stock_modal = false;
        $this->menu_quantity = '';

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Stock added successfully',
        );
    }

    public function render()
    {
        return view('livewire.frontdesk.food.inventory', [
            'menus' => $this->record
                ? FrontdeskMenu::where('frontdesk_category_id', $this->record->id)
                    ->where('branch_id', auth()->user()->branch_id)
                    ->get()
                : [],
        ]);
    }
}
