<?php

namespace App\Http\Livewire\Admin\Manage;

use Livewire\Component;
use WireUi\Traits\Actions;
use App\Models\Branch;
use App\Models\DiscountConfiguration;
use App\Models\Type;
use App\Models\StayingHour;

class Discount extends Component
{
    use Actions;

    public $branch_id;
    public $branch_name;
    public $discountAmount;
    public $discountEnabled;
    public $filter_type_id = '';

    public function mount()
    {
        if (auth()->user()->hasRole('admin')) {
            $this->branch_id = auth()->user()->branch_id;
            $this->branch_name = Branch::where('id', $this->branch_id)->value('name');
        }

        $this->loadBranchSettings();
        $this->ensureAllCombinationsExist();
    }

    public function updatedBranchId()
    {
        $this->branch_name = Branch::where('id', $this->branch_id)->value('name');
        $this->loadBranchSettings();
        $this->ensureAllCombinationsExist();
    }

    private function loadBranchSettings()
    {
        $branch = Branch::find($this->branch_id);
        $this->discountAmount = $branch?->discount_amount ?? 0;
        $this->discountEnabled = $branch?->discount_enabled ?? false;
    }

    private function ensureAllCombinationsExist()
    {
        if (!$this->branch_id) {
            return;
        }

        $types = Type::where('branch_id', $this->branch_id)->get();
        $stayingHours = StayingHour::where('branch_id', $this->branch_id)->get();

        foreach ($types as $type) {
            foreach ($stayingHours as $sh) {
                DiscountConfiguration::firstOrCreate([
                    'branch_id' => $this->branch_id,
                    'type_id' => $type->id,
                    'staying_hour_id' => $sh->id,
                ], [
                    'is_enabled' => false,
                ]);
            }
        }
    }

    public function saveGlobalSettings()
    {
        $this->validate([
            'discountAmount' => 'required|numeric|min:0',
        ]);

        Branch::where('id', $this->branch_id)->update([
            'discount_amount' => $this->discountAmount,
            'discount_enabled' => $this->discountEnabled,
        ]);

        $this->dialog()->success(
            $title = 'Saved',
            $description = 'Discount settings updated.'
        );
    }

    public function toggleConfiguration($id)
    {
        $config = DiscountConfiguration::where('id', $id)
            ->where('branch_id', $this->branch_id)
            ->first();

        if ($config) {
            $config->update(['is_enabled' => !$config->is_enabled]);
        }
    }

    public function render()
    {
        $configurations = DiscountConfiguration::where('branch_id', $this->branch_id)
            ->when($this->filter_type_id, fn ($q) => $q->where('type_id', $this->filter_type_id))
            ->with(['type', 'stayingHour'])
            ->get()
            ->sortBy([
                ['type.name', 'asc'],
                ['stayingHour.number', 'asc'],
            ]);

        return view('livewire.admin.manage.discount', [
            'configurations' => $configurations,
            'branches' => auth()->user()->hasRole('superadmin') ? Branch::all() : collect(),
            'types' => Type::where('branch_id', $this->branch_id)->get(),
        ]);
    }
}
