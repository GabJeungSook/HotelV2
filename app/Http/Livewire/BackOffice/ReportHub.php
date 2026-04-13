<?php

namespace App\Http\Livewire\BackOffice;

use Livewire\Component;

class ReportHub extends Component
{
     // optional: keep selection in URL for refresh/share
    public string $report = 'sales-v2';

    protected $queryString = [
        'report' => ['except' => 'sales-v2'],
    ];

    public function getReportsProperty(): array
    {
        // Key => config used by UI + dynamic component
        return [
            'sales-v2' => [
                'label' => 'Sales Report',
                'component' => 'back-office.sales-report-v2',
            ],
            'frontdesk-v2' => [
                'label' => 'Frontdesk Report',
                'component' => 'back-office.frontdesk-report-v2',
            ],
            'new-guest' => [
                'label' => 'New Guest Report',
                'component' => 'back-office.reports.new-guest-report',
            ],
            'check-out' => [
                'label' => 'Check-out Guest Report',
                'component' => 'back-office.reports.checkout-guest',
            ],
            'guest-per-room-type' => [
                'label' => 'Guest per Room Type Report',
                'component' => 'back-office.reports.guest-per-room-type',
            ],
            'room-boy' => [
                'label' => 'Room Boy Report',
                'component' => 'back-office.reports.room-boy-report',
            ],
            'extended' => [
                'label' => 'Extended Guest Report',
                'component' => 'back-office.reports.extended-guest-report',
            ],
            'inventory' => [
                'label' => 'Inventory Report',
                'component' => 'back-office.inventory-report',
            ],
            'big-boss' => [
                'label' => 'Big Boss Report',
                'component' => 'back-office.reports.big-boss-report',
            ],
            'frontdesk-logs' => [
                'label' => 'Frontdesk Logs',
                'component' => 'back-office.reports.frontdesk-logs',
            ],
        ];
    }

    public function getActiveComponentProperty(): ?string
    {
        return $this->reports[$this->report]['component'] ?? null;
    }
    public function render()
    {
        return view('livewire.back-office.report-hub');
    }
}
