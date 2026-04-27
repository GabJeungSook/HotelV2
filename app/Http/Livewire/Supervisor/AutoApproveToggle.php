<?php

namespace App\Http\Livewire\Supervisor;

use App\Models\Branch;
use Livewire\Component;
use WireUi\Traits\Actions;

class AutoApproveToggle extends Component
{
    use Actions;

    public $enabled = false;

    protected $listeners = ['autoApproveChanged' => 'syncState'];

    public function syncState($enabled)
    {
        $this->enabled = $enabled;
    }

    public function mount()
    {
        $branch = Branch::find(auth()->user()->branch_id);
        $this->enabled = $branch->auto_approve_enabled ?? false;
    }

    public function toggleAutoApprove()
    {
        if ($this->enabled) {
            // Currently enabled, confirm disable
            $this->dialog()->confirm([
                'title' => 'Disable Auto-Approve?',
                'description' => 'Transfer/Cancel requests will require manual supervisor approval.',
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
                'title' => 'Enable Auto-Approve?',
                'description' => 'All transfer/cancel requests will be automatically approved without supervisor review. Are you sure?',
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
        $branch->update([
            'auto_approve_enabled' => true,
            'auto_approve_enabled_by' => auth()->id(),
        ]);
        $this->enabled = true;

        // Emit event to sync other toggle instances
        $this->emit('autoApproveChanged', true);

        $this->dialog()->success(
            $title = 'Auto-Approve Enabled',
            $description = 'Transfer/Cancel requests will be auto-approved.'
        );
    }

    public function confirmDisable()
    {
        $branch = Branch::find(auth()->user()->branch_id);
        $branch->update([
            'auto_approve_enabled' => false,
            'auto_approve_enabled_by' => null,
        ]);
        $this->enabled = false;

        // Emit event to sync other toggle instances
        $this->emit('autoApproveChanged', false);

        $this->dialog()->info(
            $title = 'Auto-Approve Disabled',
            $description = 'Transfer/Cancel requests require manual approval.'
        );
    }

    public function render()
    {
        return view('livewire.supervisor.auto-approve-toggle');
    }
}
