<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\Branch;
use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\Guest;
use App\Models\Inventory;
use App\Models\Menu;
use App\Models\ParentCategory;
use App\Models\PosOrder;
use App\Models\PubInventory;
use App\Models\PubMenu;
use App\Models\Room;
use App\Models\ShiftLog;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ShiftResolver;
use App\Support\ShiftSessionGrouper;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Per-shift POS report for Big Boss. Sibling of BigBossReport, kept
 * separate so neither report can break the other. Renders two sections:
 *
 *  1. POS Sales — every pos_order rung in the shift session, with
 *     payment type (cash/room), items, totals, voided indicator.
 *  2. Inventory — opening / IN / OUT / closing per item touched in the
 *     shift's time window (across frontdesk + kitchen + pub sources).
 *
 * Per-shift "session" semantics match BigBossReport: when multiple
 * frontdesks share a shift their shift_logs are aggregated by date+type.
 */
class BigBossPosReport extends Component
{
    public $weekStart = null;
    public $selectedShiftLogId;
    public array $availableShiftSessions = [];

    public function mount(): void
    {
        $this->loadAvailableShiftSessions();
        if (!empty($this->availableShiftSessions)) {
            $this->selectedShiftLogId = end($this->availableShiftSessions)['id'];
        }
    }

    public function updatedSelectedShiftLogId(): void
    {
        // triggers re-render
    }

    public function exportHtml()
    {
        $session = $this->getSelectedSession();
        if (!$session) {
            return null;
        }

        $reportData = $this->generateReport($session);
        $branch = Branch::find(auth()->user()->branch_id);
        $branchName = $branch->name ?? 'Branch';

        $html = view('livewire.back-office.reports.big-boss-pos-report-export', array_merge(
            [
                'selectedSession' => $session,
                'branchName'      => $branchName,
            ],
            $reportData
        ))->render();

        $shiftDate = $session['shift_date'] ?? now()->format('Y-m-d');
        $shiftType = $session['shift_type'] ?? 'AM';
        $filename = "{$branchName} POS {$shiftDate} {$shiftType} SHIFT.html";

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    public function render()
    {
        $session = $this->getSelectedSession();
        $reportData = $this->generateReport($session);

        return view('livewire.back-office.reports.big-boss-pos-report', array_merge(
            ['selectedSession' => $session],
            $reportData
        ));
    }

    public function generateReport(?array $session): array
    {
        if (!$session) {
            return [
                'posSales'        => ['orders' => [], 'totals' => $this->emptyPosTotals()],
                'inventoryRows'   => [],
                'stocksInventory' => [],
                'stocksSummary'   => [],
            ];
        }

        $branchId = auth()->user()->branch_id;

        $stocksData = $this->stocksInventoryRows($session, $branchId);

        return [
            'posSales'        => $this->posSalesRows($session, $branchId),
            'inventoryRows'   => $this->inventoryRows($session, $branchId),
            'stocksInventory' => $stocksData['groups'],
            'stocksSummary'   => $stocksData['summary'],
        ];
    }

    /**
     * Per-order POS sales for the shift session, plus aggregated totals.
     */
    public function posSalesRows(array $session, int $branchId): array
    {
        $logIds = $session['log_ids'] ?? [];
        if (empty($logIds)) {
            return ['orders' => [], 'totals' => $this->emptyPosTotals()];
        }

        $posOrders = PosOrder::query()
            ->whereIn('shift_log_id', $logIds)
            ->where('branch_id', $branchId)
            ->with(['lineItems' => fn ($query) => $query->orderBy('id')])
            ->orderBy('created_at')
            ->get();

        $userNames  = User::whereIn('id', $posOrders->pluck('user_id')->unique()->filter())->pluck('name', 'id');
        $guestNames = Guest::whereIn('id', $posOrders->pluck('guest_id')->unique()->filter())->pluck('name', 'id');
        $roomNumbers = Room::whereIn('id', $posOrders->pluck('room_id')->unique()->filter())->pluck('number', 'id');

        $orders = $posOrders->map(function ($order) use ($userNames, $guestNames, $roomNumbers) {
            $items = $order->lineItems->map(
                fn ($line) => $line->item_name . ' x' . rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.')
            )->implode(', ');

            $type = $order->payment_method === 'cash'
                ? 'CASH'
                : (($order->guest_id !== null) ? 'ROOM' : 'OTHER');

            return [
                'id'          => $order->id,
                'time'        => $order->created_at?->format('g:i A'),
                'cashier'     => $userNames[$order->user_id] ?? '-',
                'type'        => $type,
                'guest'       => $order->guest_id ? ($guestNames[$order->guest_id] ?? null) : null,
                'room_number' => $order->room_id ? ($roomNumbers[$order->room_id] ?? null) : null,
                'items'       => $items,
                'subtotal'    => (int) $order->subtotal,
                'discount'    => (int) $order->discount_amount,
                'discount_reason' => $order->discount_reason,
                'total'       => (int) $order->total,
                'voided'      => $order->voided_at !== null,
            ];
        })->values()->toArray();

        $live = $posOrders->whereNull('voided_at');
        $cashTotal = (int) $live->where('payment_method', 'cash')->sum('total');
        $roomTotal = (int) $live->whereNull('payment_method')->sum('total');

        return [
            'orders' => $orders,
            'totals' => [
                'cash'         => $cashTotal,
                'room'         => $roomTotal,
                'gross'        => $cashTotal + $roomTotal,
                'live_count'   => $live->count(),
                'voided_count' => $posOrders->whereNotNull('voided_at')->count(),
            ],
        ];
    }

    /**
     * Per-item inventory movement for the shift's time window. Touches
     * every (source_type, inventory_id) that had at least one movement
     * inside [time_in, time_out]. Items with no shift activity are
     * omitted — keeps the report focused on what actually moved.
     */
    public function inventoryRows(array $session, int $branchId): array
    {
        $timeIn  = Carbon::parse($session['time_in']);
        $timeOut = Carbon::parse($session['time_out']);

        $touched = StockMovement::where('branch_id', $branchId)
            ->whereBetween('created_at', [$timeIn, $timeOut])
            ->select('source_type', 'inventory_id', 'menu_id')
            ->distinct()
            ->get();

        $rows = [];
        foreach ($touched as $key) {
            $sourceType  = $key->source_type;
            $inventoryId = $key->inventory_id;

            $opening = $this->openingBalance($sourceType, $inventoryId, $timeIn);

            $movements = StockMovement::where('source_type', $sourceType)
                ->where('inventory_id', $inventoryId)
                ->whereBetween('created_at', [$timeIn, $timeOut])
                ->orderBy('id')
                ->get();

            $inQty  = (float) $movements->whereIn('type', [StockMovement::TYPE_IN, StockMovement::TYPE_VOID])->sum('quantity');
            $outQty = (float) $movements->where('type', StockMovement::TYPE_OUT)->sum('quantity');

            $closing = $movements->isNotEmpty()
                ? (float) $movements->last()->balance_after
                : $opening;

            $rows[] = [
                'source_type' => $sourceType,
                'item_name'   => $this->resolveMenuName($sourceType, $key->menu_id),
                'opening'     => $opening,
                'in'          => $inQty,
                'out'         => $outQty,
                'closing'     => $closing,
                'movements'   => $movements->count(),
            ];
        }

        usort($rows, function ($a, $b) {
            return [$a['source_type'], strtolower($a['item_name'])]
                <=> [$b['source_type'], strtolower($b['item_name'])];
        });

        return $rows;
    }

    /**
     * Grouped stocks inventory matching the old Z-read format.
     * Groups items by parent category (FOODS/DRINKS) → sub-category,
     * with stock flow, price, sold qty, and sales totals.
     */
    public function stocksInventoryRows(array $session, int $branchId): array
    {
        $timeIn  = Carbon::parse($session['time_in']);
        $timeOut = Carbon::parse($session['time_out']);

        // Load ALL frontdesk inventory items (not just those with movements)
        $allInventory = FrontdeskInventory::where('branch_id', $branchId)->get();

        if ($allInventory->isEmpty()) {
            return ['groups' => [], 'summary' => []];
        }

        $menuIds = $allInventory->pluck('frontdesk_menu_id')->unique()->filter()->values();
        $menus = FrontdeskMenu::whereIn('id', $menuIds)
            ->with(['frontdeskCategory.parentCategory'])
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($allInventory as $inv) {
            $menu = $menus[$inv->frontdesk_menu_id] ?? null;
            if (!$menu) continue;

            $category       = $menu->frontdeskCategory;
            $parentCategory = $category?->parentCategory;
            $parentName     = $parentCategory?->name ?? 'UNCATEGORIZED';
            $categoryName   = $category?->name ?? 'UNCATEGORIZED';

            // Kitchen (warehouse) opening balance
            $prevKitchen = StockMovement::where('source_type', StockMovement::SOURCE_KITCHEN)
                ->where('inventory_id', $inv->id)
                ->where('created_at', '<', $timeIn)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->first();
            $kitchenOpening = $prevKitchen ? (float) $prevKitchen->balance_after : 0.0;

            // Kitchen movements during shift
            $kitchenMovements = StockMovement::where('source_type', StockMovement::SOURCE_KITCHEN)
                ->where('inventory_id', $inv->id)
                ->whereBetween('created_at', [$timeIn, $timeOut])
                ->orderBy('id')
                ->get();

            $kitchenIn  = (float) $kitchenMovements->whereIn('type', [StockMovement::TYPE_IN, StockMovement::TYPE_VOID])->sum('quantity');
            $kitchenOut = (float) $kitchenMovements->where('type', StockMovement::TYPE_OUT)->sum('quantity');
            $kitchenClosing = $kitchenMovements->isNotEmpty() ? (float) $kitchenMovements->last()->balance_after : $kitchenOpening;

            // Frontdesk opening balance
            $prevFrontdesk = StockMovement::where('source_type', StockMovement::SOURCE_FRONTDESK)
                ->where('inventory_id', $inv->id)
                ->where('created_at', '<', $timeIn)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->first();
            $frontdeskOpening = $prevFrontdesk ? (float) $prevFrontdesk->balance_after : 0.0;

            // Frontdesk movements during shift
            $frontdeskMovements = StockMovement::where('source_type', StockMovement::SOURCE_FRONTDESK)
                ->where('inventory_id', $inv->id)
                ->whereBetween('created_at', [$timeIn, $timeOut])
                ->orderBy('id')
                ->get();

            $frontdeskIn  = (float) $frontdeskMovements->whereIn('type', [StockMovement::TYPE_IN, StockMovement::TYPE_VOID])->sum('quantity');
            $frontdeskOut = (float) $frontdeskMovements->where('type', StockMovement::TYPE_OUT)->sum('quantity');
            $frontdeskClosing = $frontdeskMovements->isNotEmpty() ? (float) $frontdeskMovements->last()->balance_after : $frontdeskOpening;

            $price      = (float) $menu->price;
            $stockSold  = $frontdeskOut;
            $salesTotal = $stockSold * $price;

            $items[] = [
                'parent_category' => strtoupper($parentName),
                'category'        => strtoupper($categoryName),
                'name'            => $menu->name,
                'price'           => $price,
                'kitchen'         => ['opening' => $kitchenOpening, 'in' => $kitchenIn, 'out' => $kitchenOut, 'closing' => $kitchenClosing],
                'frontdesk'       => ['opening' => $frontdeskOpening, 'in' => $frontdeskIn, 'out' => $frontdeskOut, 'closing' => $frontdeskClosing],
                'total_remaining' => $kitchenClosing + $frontdeskClosing,
                'combo_ded'       => 0,
                'stock_sold'      => $stockSold,
                'sales_total'     => $salesTotal,
            ];
        }

        // Group by parent category → sub-category
        $groups = [];
        foreach ($items as $item) {
            $pc  = $item['parent_category'];
            $cat = $item['category'];

            if (!isset($groups[$pc])) {
                $groups[$pc] = ['categories' => [], 'total_sales' => 0];
            }
            if (!isset($groups[$pc]['categories'][$cat])) {
                $groups[$pc]['categories'][$cat] = ['items' => []];
            }

            $groups[$pc]['categories'][$cat]['items'][] = $item;
            $groups[$pc]['total_sales'] += $item['sales_total'];
        }

        // Sort categories and items alphabetically
        foreach ($groups as &$group) {
            ksort($group['categories']);
            foreach ($group['categories'] as &$cat) {
                usort($cat['items'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
            }
        }
        unset($group, $cat);

        // Build summary
        $summary = [];
        $grandTotal = 0;
        foreach ($groups as $parentName => $group) {
            $summary[] = ['label' => "TOTAL {$parentName} SALES", 'amount' => $group['total_sales']];
            $grandTotal += $group['total_sales'];
        }
        $summary[] = ['label' => 'TOTAL SALES', 'amount' => $grandTotal];

        return ['groups' => $groups, 'summary' => $summary];
    }

    private function openingBalance(string $sourceType, ?int $inventoryId, Carbon $timeIn): float
    {
        $previous = StockMovement::where('source_type', $sourceType)
            ->where('inventory_id', $inventoryId)
            ->where('created_at', '<', $timeIn)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $previous ? (float) $previous->balance_after : 0.0;
    }

    private function resolveMenuName(string $sourceType, ?int $menuId): string
    {
        if ($menuId === null) return '(unknown item)';

        $menu = match ($sourceType) {
            StockMovement::SOURCE_FRONTDESK => FrontdeskMenu::find($menuId),
            StockMovement::SOURCE_KITCHEN   => Menu::find($menuId),
            StockMovement::SOURCE_PUB       => PubMenu::find($menuId),
            default                         => null,
        };

        return $menu?->name ?? "(menu #{$menuId})";
    }

    private function emptyPosTotals(): array
    {
        return [
            'cash'         => 0,
            'room'         => 0,
            'gross'        => 0,
            'live_count'   => 0,
            'voided_count' => 0,
        ];
    }

    /**
     * Per-shift session selector — same pattern as BigBossReport so the
     * dropdown lists exactly the same shift sessions as the main report.
     */
    private function loadAvailableShiftSessions(): void
    {
        $weekStart = $this->weekStart ? Carbon::parse($this->weekStart)->startOfWeek() : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $shiftLogs = ShiftLog::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->whereNotNull('time_out')
            ->whereBetween('time_in', [$weekStart, $weekEnd])
            ->with('frontdesk:id,name')
            ->get();

        $this->availableShiftSessions = ShiftSessionGrouper::group($shiftLogs);
    }

    private function getShiftType(Carbon $timeIn): string
    {
        return ShiftResolver::fromClock($timeIn);
    }

    private function shiftTypeOf(ShiftLog $log): string
    {
        return $log->shift ?? ShiftResolver::fromClock($log->time_in);
    }

    private function getSelectedSession(): ?array
    {
        return collect($this->availableShiftSessions)->firstWhere('id', $this->selectedShiftLogId);
    }
}
