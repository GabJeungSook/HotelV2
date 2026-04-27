<?php

namespace App\Http\Livewire\Supervisor;

use App\Models\Branch;
use Livewire\Component;
use WireUi\Traits\Actions;

class ForceAutoOverrideToggle extends Component
{
    use Actions;

    public $enabled = false;

    protected $listeners = ['forceAutoOverrideChanged' => 'syncState'];

    public function syncState($enabled)
    {
        $this->enabled = $enabled;
    }

    public function mount()
    {
        $branch = Branch::find(auth()->user()->branch_id);
        $this->enabled = $branch->force_auto_override ?? false;
    }

    public function toggleAutoOverride()
    {
        if ($this->enabled) {
            // Currently enabled, confirm disable
            $this->dialog()->confirm([
                'title' => 'Disable Auto-Override?',
                'description' => 'Override requests will require manual supervisor approval.',
                'icon' => 'question',
                'accept' => [
                    'label' => 'Yes, Disable',
                    'method' => 'confirmDisable',
                ],
                'reject' => [
                    'label' => 'Cancel',
                ],
            ]);
        } else {
            // Currently disabled, confirm enable
            $this->dialog()->confirm([
                'title' => 'Enable Auto-Override?',
                'description' => 'All override requests will be automatically approved without supervisor review. Are you sure?',
                'icon' => 'warning',
                'accept' => [
                    'label' => 'Yes, Enable',
                    'method' => 'confirmEnable',
                ],
                'reject' => [
                    'label' => 'Cancel',
                ],
            ]);
        }
    }

    public function confirmEnable()
    {
        $branch = Branch::find(auth()->user()->branch_id);
        $branch->update(['force_auto_override' => true]);
        $this->enabled = true;

        // Emit event to sync other toggle instances
        $this->emit('forceAutoOverrideChanged', true);

        $this->dialog()->success(
            $title = 'Auto-Override Enabled',
            $description = 'Override requests will be auto-approved.'
        );
    }

    public function confirmDisable()
    {
        $branch = Branch::find(auth()->user()->branch_id);
        $branch->update(['force_auto_override' => false]);
        $this->enabled = false;

        // Emit event to sync other toggle instances
        $this->emit('forceAutoOverrideChanged', false);

        $this->dialog()->info(
            $title = 'Auto-Override Disabled',
            $description = 'Override requests require manual approval.'
        );
    }

    public function render()
    {
        return view('livewire.supervisor.force-auto-override-toggle');
    }
}
