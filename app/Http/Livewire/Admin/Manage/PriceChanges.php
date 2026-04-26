<?php

namespace App\Http\Livewire\Admin\Manage;

use App\Models\FrontdeskMenu;
use App\Models\Menu;
use App\Models\MenuPriceChange;
use App\Models\PubMenu;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin viewer for menu_price_changes — every UPDATE on a menu's price
 * (or name) goes through MenuPriceObserver and writes a row here.
 * Shows: when, source, item, field, old value, new value, who.
 */
class PriceChanges extends Component
{
    use WithPagination;

    public string $sourceFilter = 'all';
    public string $fieldFilter  = 'all';
    public string $search   = '';
    public ?string $dateFrom    = null;
    public ?string $dateTo      = null;

    protected $queryString = [
        'sourceFilter' => ['except' => 'all'],
        'fieldFilter'  => ['except' => 'all'],
        'search'   => ['except' => ''],
        'dateFrom'     => ['except' => null],
        'dateTo'       => ['except' => null],
    ];

    public function mount(): void
    {
        // Price changes are low-frequency (admin rarely edits prices),
        // so default to the last 30 days — week is too narrow and often
        // would render empty.
        $this->dateFrom = $this->dateFrom ?? Carbon::now()->subDays(30)->toDateString();
        $this->dateTo   = $this->dateTo   ?? Carbon::now()->toDateString();
    }

    public function updated($field): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->sourceFilter = 'all';
        $this->fieldFilter  = 'all';
        $this->search   = '';
        $this->dateFrom     = Carbon::now()->subDays(30)->toDateString();
        $this->dateTo       = Carbon::now()->toDateString();
        $this->resetPage();
    }

    public function render()
    {
        $searchTerm = trim($this->search);

        // Resolve search → menu_id list per source + user_id list (matches
        // by item name OR who changed it). One field, multi-target search.
        $matchingMenuIds = [];
        $matchingUserIds = [];
        if ($searchTerm !== '') {
            $term = '%' . $searchTerm . '%';
            $matchingMenuIds = [
                'frontdesk' => FrontdeskMenu::where('name', 'like', $term)->pluck('id')->all(),
                'kitchen'   => Menu::where('name', 'like', $term)->pluck('id')->all(),
                'pub'       => PubMenu::where('name', 'like', $term)->pluck('id')->all(),
            ];
            $matchingUserIds = User::where('name', 'like', $term)->pluck('id')->all();
        }

        $query = MenuPriceChange::query()
            ->when($this->sourceFilter !== 'all', fn ($q) => $q->where('source_type', $this->sourceFilter))
            ->when($this->fieldFilter  !== 'all', fn ($q) => $q->where('field', $this->fieldFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($searchTerm !== '', function ($q) use ($matchingMenuIds, $matchingUserIds) {
                $q->where(function ($outer) use ($matchingMenuIds, $matchingUserIds) {
                    foreach ($matchingMenuIds as $source => $ids) {
                        if (empty($ids)) continue;
                        $outer->orWhere(function ($inner) use ($source, $ids) {
                            $inner->where('source_type', $source)->whereIn('menu_id', $ids);
                        });
                    }
                    if (!empty($matchingUserIds)) {
                        $outer->orWhereIn('changed_by_user_id', $matchingUserIds);
                    }
                });
            })
            ->orderByDesc('id');

        $changes = $query->paginate(25);

        // Bulk-resolve item names + actor names for the visible page.
        $bySource = $changes->groupBy('source_type')
            ->map(fn ($rows) => $rows->pluck('menu_id')->unique()->filter()->all());

        $itemNames = [
            'frontdesk' => isset($bySource['frontdesk']) ? FrontdeskMenu::whereIn('id', $bySource['frontdesk'])->pluck('name', 'id')->all() : [],
            'kitchen'   => isset($bySource['kitchen'])   ? Menu::whereIn('id', $bySource['kitchen'])->pluck('name', 'id')->all() : [],
            'pub'       => isset($bySource['pub'])       ? PubMenu::whereIn('id', $bySource['pub'])->pluck('name', 'id')->all() : [],
        ];

        $userIds = $changes->pluck('changed_by_user_id')->unique()->filter()->all();
        $userNames = $userIds ? User::whereIn('id', $userIds)->pluck('name', 'id')->all() : [];

        return view('livewire.admin.manage.price-changes', [
            'changes'   => $changes,
            'itemNames' => $itemNames,
            'userNames' => $userNames,
            'sources'   => [
                'all'       => 'All sources',
                'frontdesk' => 'Frontdesk',
                'kitchen'   => 'Kitchen',
                'pub'       => 'Pub',
            ],
            'fields' => [
                'all'   => 'All changes',
                'price' => 'Price changes',
                'name'  => 'Name changes',
            ],
        ]);
    }
}
