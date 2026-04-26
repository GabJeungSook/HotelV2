<?php

namespace App\Http\Livewire\Admin\Manage;

use App\Models\FrontdeskMenu;
use App\Models\Menu;
use App\Models\PubMenu;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin viewer for the stock_movements audit log.
 * Shows every IN / OUT / ADJUST / VOID / OPENING with the actor, item,
 * quantity, balance after, reason, and a backref (e.g. transaction id).
 *
 * Filters: source (frontdesk/kitchen/pub), type, date range, item search.
 */
class StockMovements extends Component
{
    use WithPagination;

    public string $sourceFilter = 'all';
    public string $typeFilter   = 'all';
    /**
     * Combined search box. Matches against item name (any source), user
     * name, reason text, and ref_id (transaction id). One field is easier
     * for the user than four separate inputs.
     */
    public string $search       = '';
    public ?string $dateFrom    = null;
    public ?string $dateTo      = null;

    protected $queryString = [
        'sourceFilter' => ['except' => 'all'],
        'typeFilter'   => ['except' => 'all'],
        'search'       => ['except' => ''],
        'dateFrom'     => ['except' => null],
        'dateTo'       => ['except' => null],
    ];

    public function mount(): void
    {
        // Default to this week (Monday → today) so the first load shows
        // the current week's activity without having to pick dates.
        $this->dateFrom = $this->dateFrom ?? Carbon::now()->startOfWeek()->toDateString();
        $this->dateTo   = $this->dateTo   ?? Carbon::now()->toDateString();
    }

    public function updated($field): void
    {
        // Any filter change resets pagination.
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->sourceFilter = 'all';
        $this->typeFilter   = 'all';
        $this->search       = '';
        $this->dateFrom     = Carbon::now()->startOfWeek()->toDateString();
        $this->dateTo       = Carbon::now()->toDateString();
        $this->resetPage();
    }

    public function render()
    {
        $branchId = auth()->user()->branch_id;
        $searchTerm = trim($this->search);

        // Resolve search → menu_id list per source + user_id list, so we
        // can filter without a complex polymorphic JOIN.
        $matchingMenuIds = [];
        $matchingUserIds = [];
        if ($searchTerm !== '') {
            $term = '%' . $searchTerm . '%';

            $matchingMenuIds = [
                StockMovement::SOURCE_FRONTDESK => FrontdeskMenu::where('branch_id', $branchId)
                    ->where('name', 'like', $term)->pluck('id')->all(),
                StockMovement::SOURCE_KITCHEN => Menu::where('branch_id', $branchId)
                    ->where('name', 'like', $term)->pluck('id')->all(),
                StockMovement::SOURCE_PUB => PubMenu::where('branch_id', $branchId)
                    ->where('name', 'like', $term)->pluck('id')->all(),
            ];

            $matchingUserIds = User::where('branch_id', $branchId)
                ->where('name', 'like', $term)->pluck('id')->all();
        }

        $query = StockMovement::query()
            ->where('branch_id', $branchId)
            ->when($this->sourceFilter !== 'all', fn ($q) => $q->where('source_type', $this->sourceFilter))
            ->when($this->typeFilter   !== 'all', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($searchTerm !== '', function ($q) use ($matchingMenuIds, $matchingUserIds, $searchTerm) {
                $q->where(function ($outer) use ($matchingMenuIds, $matchingUserIds, $searchTerm) {
                    // Match by item name (any source)
                    foreach ($matchingMenuIds as $source => $ids) {
                        if (empty($ids)) continue;
                        $outer->orWhere(function ($inner) use ($source, $ids) {
                            $inner->where('source_type', $source)->whereIn('menu_id', $ids);
                        });
                    }
                    // Match by user name (the actor)
                    if (!empty($matchingUserIds)) {
                        $outer->orWhereIn('user_id', $matchingUserIds);
                    }
                    // Match by reason text (free-text reason on the row)
                    $outer->orWhere('reason', 'like', '%' . $searchTerm . '%');
                    // Match by ref_id (transaction id) — only if numeric
                    if (is_numeric($searchTerm)) {
                        $outer->orWhere('ref_id', (int) $searchTerm);
                    }
                });
            })
            ->orderByDesc('id');

        $movements = $query->paginate(25);

        // Bulk-resolve item names + actor names for the visible page.
        $byMenuSource = $movements->groupBy('source_type')
            ->map(fn ($rows) => $rows->pluck('menu_id')->unique()->filter()->all());

        $itemNames = [
            StockMovement::SOURCE_FRONTDESK => isset($byMenuSource[StockMovement::SOURCE_FRONTDESK])
                ? FrontdeskMenu::whereIn('id', $byMenuSource[StockMovement::SOURCE_FRONTDESK])->pluck('name', 'id')->all()
                : [],
            StockMovement::SOURCE_KITCHEN => isset($byMenuSource[StockMovement::SOURCE_KITCHEN])
                ? Menu::whereIn('id', $byMenuSource[StockMovement::SOURCE_KITCHEN])->pluck('name', 'id')->all()
                : [],
            StockMovement::SOURCE_PUB => isset($byMenuSource[StockMovement::SOURCE_PUB])
                ? PubMenu::whereIn('id', $byMenuSource[StockMovement::SOURCE_PUB])->pluck('name', 'id')->all()
                : [],
        ];

        $userIds = $movements->pluck('user_id')->unique()->filter()->all();
        $userNames = $userIds
            ? User::whereIn('id', $userIds)->pluck('name', 'id')->all()
            : [];

        return view('livewire.admin.manage.stock-movements', [
            'movements'  => $movements,
            'itemNames'  => $itemNames,
            'userNames'  => $userNames,
            'sources'    => [
                'all'  => 'All sources',
                StockMovement::SOURCE_FRONTDESK => 'Frontdesk',
                StockMovement::SOURCE_KITCHEN   => 'Kitchen',
                StockMovement::SOURCE_PUB       => 'Pub',
            ],
            'types' => [
                'all'                       => 'All types',
                StockMovement::TYPE_IN      => 'IN (received)',
                StockMovement::TYPE_OUT     => 'OUT (sold)',
                StockMovement::TYPE_VOID    => 'VOID (reversed)',
                StockMovement::TYPE_ADJUST  => 'ADJUST (corrected)',
                StockMovement::TYPE_OPENING => 'OPENING (initial)',
            ],
        ]);
    }
}
