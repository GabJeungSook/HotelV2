<?php

namespace App\Http\Livewire\Admin\Manage;

use App\Models\ActivityLog;
use Livewire\Component;
use WireUi\Traits\Actions;
use App\Models\FrontdeskMenu;
use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\StockMovement;
use App\Services\Pos\StockService;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;

class KitchenInventory extends Component
{
    public $categories;
    public $selectedCategoryId = null;
    public $selectedCategory;
    public $menu;
    public $menu_item;
    public $menu_name;
    public $menu_price;
    public $menu_quantity;

    public $add_modal = false;
    public $edit_modal = false;
    public $item_code;
    public $name;
    public $price;
    public $image;
    public $branch_id;

    public $selectedMenu;

    use Actions;
    use WithFileUploads;

    public function mount()
    {
        if(auth()->user()->hasRole('superadmin'))
        {
            $this->categories = FrontdeskCategory::where('branch_id', $this->branch_id)->get();
        }else{
            $this->categories = FrontdeskCategory::where('branch_id', auth()->user()->branch_id)->get();
        }

        // Check for category parameter from URL (for returning from Manage Inventory)
        $categoryParam = request()->query('category');
        if ($categoryParam && $this->categories->contains('id', $categoryParam)) {
            $this->selectedCategoryId = (int) $categoryParam;
            $this->selectedCategory = $this->categories->firstWhere('id', $categoryParam);
        } else {
            $this->selectedCategoryId = $this->categories->first()->id ?? null;
            $this->selectedCategory = $this->categories->first() ?? null;
        }
    }

    public function updatedBranchId($value)
    {
       $this->mount();
    }

    public function selectCategory($categoryId)
    {
        $this->selectedCategoryId = $categoryId;
        $this->menu = FrontdeskMenu::where('frontdesk_category_id', $this->selectedCategoryId)
            ->get();
        $this->selectedCategory = FrontdeskCategory::find($categoryId);
    }

     public function addMenu()
    {
        $this->reset(['item_code','name', 'price', 'image']);
        $this->add_modal = true;
    }

     public function saveMenu()
    {
        $this->validate([
            'item_code' => 'required',
            'name' => 'required',
            'price' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png|max:25000',
        ], [
            'item_code.required' => 'Please enter an item code',
            'name.required' => 'Please enter a menu name',
            'price.required' => 'Please enter a price',
            'image.image' => 'The image must be a valid image file',
            'image.mimes' => 'The image must be a file of type: jpeg, png',
            'image.max' => 'The image must not be greater than 25MB',
        ]);

        DB::beginTransaction();
        $menu = FrontdeskMenu::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'item_code' => $this->item_code,
            'name' => $this->name,
            'price' => $this->price,
            'image' => $this->image ? $this->image->store('menu_images', 'public') : null,
            'frontdesk_category_id' => $this->selectedCategory->id,
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Create Menu',
            'description' => 'Created menu ' . $this->name,
        ]);

        // Auto-create a zero-stock inventory row so the new menu item
        // appears immediately on the POS register (showing as 'Out of stock'
        // until a Stock-In or admin Add Stock fills it).
        FrontdeskInventory::create([
            'branch_id'         => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'frontdesk_menu_id' => $menu->id,
            'number_of_serving' => 0,
        ]);
        DB::commit();

        $this->add_modal = false;
        $this->reset(
            'item_code',
            'name',
            'price',
            'image',
        );

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Menu has been added'
        );
    }

      public function saveStock()
    {
        $this->validate([
            'menu_quantity' => 'required|numeric|min:1'
        ]);

        $branchId = auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id;

        // Route through StockService::in so an audited stock_movements row
        // is created. Direct $inventory->update() bypassed the audit log
        // and produced drift between inventory and movement totals; this
        // is the same fix class as the Kitchen/Pub Phase A refactor.
        try {
            app(StockService::class)->in(
                StockMovement::SOURCE_FRONTDESK,
                (int) $this->menu_item->id,
                (float) $this->menu_quantity,
                [
                    'branch_id' => $branchId,
                    'reason'    => 'Admin add stock',
                    'ref_type'  => 'admin_add_stock',
                    'user_id'   => auth()->id(),
                ]
            );
        } catch (\Throwable $e) {
            $this->dialog()->error(
                $title = 'Stock add failed',
                $description = $e->getMessage(),
            );
            return;
        }

        ActivityLog::create([
            'branch_id'   => $branchId,
            'user_id'     => auth()->user()->id,
            'activity'    => 'Add Inventory',
            'description' => sprintf('Added %s units to %s (admin)', rtrim(rtrim(number_format((float) $this->menu_quantity, 2), '0'), '.'), $this->menu_item->name),
        ]);

        $this->add_modal = false;
        $this->menu_quantity = '';

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Stock added successfully',
        );
    }

    public function editMenu($id)
    {
        $this->edit_modal = true;
        $this->selectedMenu = FrontdeskMenu::find($id);
        $this->item_code = $this->selectedMenu->item_code;
        $this->name = $this->selectedMenu->name;
        $this->price = $this->selectedMenu->price;
    }

    public function updateMenu(){
        $this->validate([
            'item_code' => 'required',
            'name' => 'required',
            'price' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png|max:25000',
        ], [
            'item_code.required' => 'Please enter an item code',
            'name.required' => 'Please enter a menu name',
            'price.required' => 'Please enter a price',
            'image.image' => 'The image must be a valid image file',
            'image.mimes' => 'The image must be a file of type: jpeg, png',
            'image.max' => 'The image must not be greater than 25MB',
        ]);

        $this->selectedMenu->item_code = $this->item_code;
        $this->selectedMenu->name = $this->name;
        $this->selectedMenu->price = $this->price;

        if ($this->image) {
            $this->selectedMenu->image = $this->image->store('menu_images', 'public');
        }

        $this->selectedMenu->save();

        ActivityLog::create([
            'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Update Menu',
            'description' => 'Updated menu ' . $this->name,
        ]);

        $this->edit_modal = false;
        $this->reset('name', 'price', 'image');

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Menu has been updated'
        );
    }

    public function render()
    {

        return view('livewire.admin.manage.kitchen-inventory', [
            'menus' => $this->selectedCategoryId ? FrontdeskMenu::where('frontdesk_category_id', $this->selectedCategoryId)->get() : [],
            'branches' => \App\Models\Branch::all(),
        ]);
    }
}
