<?php

namespace App\Http\Livewire\Supervisor;

use App\Models\Branch;
use Livewire\Component;
use WireUi\Traits\Actions;

class ForceAutoOverrideToggle extends Component
{
    use Actions;

    public $enabled = false;

    public function mount()
    {
        $branch = Branch::find(auth()->user()->branch_id);
        $this->enabled = $branch->force_auto_override ?? false;
    }

    public function updatedEnabled($value)
    {
        $branch = Branch::find(auth()->user()->branch_id);
        $branch->update(['force_auto_override' => $value]);

        if ($value) {
            $this->dialog()->success(
                $title = 'Auto-Override Enabled',
                $description = 'Override requests will be auto-approved.'
            );
        } else {
            $this->dialog()->info(
                $title = 'Auto-Override Disabled',
                $description = 'Override requests require manual approval.'
            );
        }
    }

    public function render()
    {
        return view('livewire.supervisor.force-auto-override-toggle');
    }
}
