<?php

namespace App\Http\Livewire\Frontdesk\Food;

use App\Models\ActivityLog;
use App\Models\FrontdeskMenu;
use App\Services\Pos\StockService;
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

        try {
            app(StockService::class)->warehouseIn(
                (int) $this->menu_item->id,
                (float) $this->menu_quantity,
                [
                    'branch_id' => auth()->user()->branch_id,
                    'reason'    => 'Add stock to kitchen',
                    'ref_type'  => 'admin_warehouse_add',
                    'user_id'   => auth()->id(),
                ]
            );
        } catch (\Throwable $e) {
            $this->dialog()->error(
                $title = 'Stock-in failed',
                $description = $e->getMessage(),
            );
            return;
        }

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Add Inventory',
            'description' => sprintf('Added %s units of %s to kitchen stock',
                rtrim(rtrim(number_format((float) $this->menu_quantity, 2), '0'), '.'),
                $this->menu_item->name),
        ]);

        $this->add_stock_modal = false;
        $this->menu_quantity = '';

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Stock added to kitchen successfully',
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
