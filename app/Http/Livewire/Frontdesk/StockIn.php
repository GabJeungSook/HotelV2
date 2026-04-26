<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\FrontdeskMenu;
use App\Models\Menu as KitchenMenu;
use App\Models\PubMenu;
use App\Models\StockMovement;
use App\Services\Pos\StockService;
use Livewire\Component;
use WireUi\Traits\Actions;

class StockIn extends Component
{
    use Actions;

    public string $source_type = StockMovement::SOURCE_FRONTDESK;
    public ?int $menu_id = null;
    public float $quantity = 0;
    public string $reason = '';

    protected $rules = [
        'source_type' => 'required|in:frontdesk,kitchen,pub',
        'menu_id'     => 'required|integer',
        'quantity'    => 'required|numeric|gt:0',
        'reason'      => 'nullable|string|max:255',
    ];

    public function updatedSourceType(): void
    {
        $this->menu_id = null;
    }

    public function submit(): void
    {
        $this->validate();

        app(StockService::class)->in(
            $this->source_type,
            (int) $this->menu_id,
            (float) $this->quantity,
            [
                'branch_id' => auth()->user()->branch_id,
                'reason'    => $this->reason !== '' ? $this->reason : null,
                'ref_type'  => 'stock_in_form',
                'user_id'   => auth()->id(),
            ]
        );

        $recorded = $this->quantity;
        $this->reset(['menu_id', 'quantity', 'reason']);
        $this->notification()->success('Stock recorded', "Recorded {$recorded} units.");
    }

    public function render()
    {
        $branchId = auth()->user()->branch_id;

        $menus = match ($this->source_type) {
            StockMovement::SOURCE_FRONTDESK => FrontdeskMenu::where('branch_id', $branchId)->orderBy('name')->get(),
            StockMovement::SOURCE_KITCHEN   => KitchenMenu::where('branch_id', $branchId)->orderBy('name')->get(),
            StockMovement::SOURCE_PUB       => PubMenu::where('branch_id', $branchId)->orderBy('name')->get(),
            default                         => collect(),
        };

        return view('livewire.frontdesk.stock-in', compact('menus'));
    }
}
