<?php

namespace App\Services\Pos;

use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private StockSourceResolver $resolver) {}

    public function in(string $sourceType, int $menuId, float $qty, array $context = []): StockMovement
    {
        return $this->apply($sourceType, $menuId, $qty, StockMovement::TYPE_IN, $context);
    }

    public function out(string $sourceType, int $menuId, float $qty, array $context = []): StockMovement
    {
        return $this->apply($sourceType, $menuId, $qty, StockMovement::TYPE_OUT, $context);
    }

    public function void(string $sourceType, int $menuId, float $qty, array $context = []): StockMovement
    {
        return $this->apply($sourceType, $menuId, $qty, StockMovement::TYPE_VOID, $context);
    }

    /**
     * Transfer stock from kitchen to frontdesk.
     * Deducts warehouse_stock and adds to number_of_serving.
     * Creates two StockMovement records: kitchen OUT + frontdesk IN.
     */
    public function transfer(int $menuId, float $qty, array $context = []): array
    {
        return DB::transaction(function () use ($menuId, $qty, $context) {
            $branchId = $context['branch_id'] ?? auth()->user()?->branch_id;

            $inventory = \App\Models\FrontdeskInventory::query()
                ->where('frontdesk_menu_id', $menuId)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                throw new \RuntimeException('Inventory record not found for this item.');
            }

            if ((float) $inventory->warehouse_stock < $qty) {
                throw new InsufficientStockException('kitchen', $menuId, (float) $inventory->warehouse_stock, $qty);
            }

            // Deduct from kitchen
            $inventory->warehouse_stock = (float) $inventory->warehouse_stock - $qty;
            // Add to frontdesk
            $inventory->number_of_serving = (float) $inventory->number_of_serving + $qty;
            $inventory->save();

            // Kitchen OUT movement
            $kitchenOut = StockMovement::create([
                'branch_id'    => $branchId,
                'source_type'  => StockMovement::SOURCE_KITCHEN,
                'menu_id'      => $menuId,
                'inventory_id' => $inventory->id,
                'type'         => StockMovement::TYPE_OUT,
                'quantity'     => $qty,
                'balance_after'=> (float) $inventory->warehouse_stock,
                'reason'       => $context['reason'] ?? 'Transfer to frontdesk',
                'ref_type'     => 'transfer_to_frontdesk',
                'ref_id'       => $context['ref_id'] ?? null,
                'user_id'      => $context['user_id'] ?? auth()->id(),
                'shift_log_id' => $context['shift_log_id'] ?? null,
            ]);

            // Frontdesk IN movement
            $frontdeskIn = StockMovement::create([
                'branch_id'    => $branchId,
                'source_type'  => StockMovement::SOURCE_FRONTDESK,
                'menu_id'      => $menuId,
                'inventory_id' => $inventory->id,
                'type'         => StockMovement::TYPE_IN,
                'quantity'     => $qty,
                'balance_after'=> (float) $inventory->number_of_serving,
                'reason'       => $context['reason'] ?? 'Transfer from kitchen',
                'ref_type'     => 'transfer_from_kitchen',
                'ref_id'       => $context['ref_id'] ?? null,
                'user_id'      => $context['user_id'] ?? auth()->id(),
                'shift_log_id' => $context['shift_log_id'] ?? null,
            ]);

            return [$kitchenOut, $frontdeskIn];
        });
    }

    /**
     * Add stock to kitchen (admin use).
     * Only increases warehouse_stock, does NOT touch number_of_serving.
     */
    public function warehouseIn(int $menuId, float $qty, array $context = []): StockMovement
    {
        return DB::transaction(function () use ($menuId, $qty, $context) {
            $branchId = $context['branch_id'] ?? auth()->user()?->branch_id;

            $inventory = \App\Models\FrontdeskInventory::query()
                ->where('frontdesk_menu_id', $menuId)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                $inventory = \App\Models\FrontdeskInventory::create([
                    'branch_id'         => $branchId,
                    'frontdesk_menu_id' => $menuId,
                    'number_of_serving' => 0,
                    'warehouse_stock'   => $qty,
                ]);
            } else {
                $inventory->warehouse_stock = (float) $inventory->warehouse_stock + $qty;
                $inventory->save();
            }

            return StockMovement::create([
                'branch_id'    => $branchId,
                'source_type'  => StockMovement::SOURCE_KITCHEN,
                'menu_id'      => $menuId,
                'inventory_id' => $inventory->id,
                'type'         => StockMovement::TYPE_IN,
                'quantity'     => $qty,
                'balance_after'=> (float) $inventory->warehouse_stock,
                'reason'       => $context['reason'] ?? 'Admin add stock to kitchen',
                'ref_type'     => $context['ref_type'] ?? 'admin_warehouse_add',
                'ref_id'       => $context['ref_id'] ?? null,
                'user_id'      => $context['user_id'] ?? auth()->id(),
                'shift_log_id' => $context['shift_log_id'] ?? null,
            ]);
        });
    }

    /**
     * Set kitchen stock to an exact quantity (admin correction).
     * Creates an ADJUST StockMovement for audit trail.
     */
    public function warehouseAdjust(int $menuId, float $newBalance, array $context = []): StockMovement
    {
        return DB::transaction(function () use ($menuId, $newBalance, $context) {
            $branchId = $context['branch_id'] ?? auth()->user()?->branch_id;

            $inventory = \App\Models\FrontdeskInventory::query()
                ->where('frontdesk_menu_id', $menuId)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->lockForUpdate()
                ->first();

            $oldBalance = 0.0;

            if (!$inventory) {
                $inventory = \App\Models\FrontdeskInventory::create([
                    'branch_id'         => $branchId,
                    'frontdesk_menu_id' => $menuId,
                    'number_of_serving' => 0,
                    'warehouse_stock'   => $newBalance,
                ]);
            } else {
                $oldBalance = (float) $inventory->warehouse_stock;
                $inventory->warehouse_stock = $newBalance;
                $inventory->save();
            }

            return StockMovement::create([
                'branch_id'    => $branchId,
                'source_type'  => StockMovement::SOURCE_KITCHEN,
                'menu_id'      => $menuId,
                'inventory_id' => $inventory->id,
                'type'         => StockMovement::TYPE_ADJUST,
                'quantity'     => abs($newBalance - $oldBalance),
                'balance_after'=> $newBalance,
                'reason'       => $context['reason'] ?? 'Admin set kitchen stock',
                'ref_type'     => $context['ref_type'] ?? 'admin_warehouse_adjust',
                'ref_id'       => $context['ref_id'] ?? null,
                'user_id'      => $context['user_id'] ?? auth()->id(),
                'shift_log_id' => $context['shift_log_id'] ?? null,
            ]);
        });
    }

    public function adjust(string $sourceType, int $menuId, float $absoluteBalance, array $context = []): StockMovement
    {
        return DB::transaction(function () use ($sourceType, $menuId, $absoluteBalance, $context) {
            $branchId = $context['branch_id'] ?? auth()->user()?->branch_id;
            $modelClass = $this->resolver->modelFor($sourceType);
            $menuFk = $this->resolver->menuForeignKey($sourceType);

            $inventory = $modelClass::query()
                ->where($menuFk, $menuId)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->lockForUpdate()
                ->first();

            if ($inventory === null) {
                $inventory = $modelClass::create([
                    'branch_id'         => $branchId,
                    $menuFk             => $menuId,
                    'number_of_serving' => $absoluteBalance,
                ]);
                $delta = $absoluteBalance;
            } else {
                $delta = $absoluteBalance - (float) $inventory->number_of_serving;
                $inventory->number_of_serving = $absoluteBalance;
                $inventory->save();
            }

            return StockMovement::create([
                'branch_id'    => $branchId,
                'source_type'  => $sourceType,
                'menu_id'      => $menuId,
                'inventory_id' => $inventory->id,
                'type'         => StockMovement::TYPE_ADJUST,
                'quantity'     => abs($delta),
                'balance_after'=> $absoluteBalance,
                'reason'       => $context['reason'] ?? null,
                'ref_type'     => $context['ref_type'] ?? null,
                'ref_id'       => $context['ref_id'] ?? null,
                'user_id'      => $context['user_id'] ?? auth()->id(),
                'shift_log_id' => $context['shift_log_id'] ?? null,
            ]);
        });
    }

    private function apply(string $sourceType, int $menuId, float $qty, string $type, array $context): StockMovement
    {
        return DB::transaction(function () use ($sourceType, $menuId, $qty, $type, $context) {
            $shadow = ($context['shadow'] ?? false) === true;
            $branchId = $context['branch_id'] ?? auth()->user()?->branch_id;
            $modelClass = $this->resolver->modelFor($sourceType);
            $menuFk     = $this->resolver->menuForeignKey($sourceType);

            $inventory = $modelClass::query()
                ->where($menuFk, $menuId)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->lockForUpdate()
                ->first();

            $available = $inventory ? (float) $inventory->number_of_serving : 0.0;

            if ($shadow) {
                $newBalance = $available;

                if ($inventory === null) {
                    $inventory = $modelClass::create([
                        'branch_id'         => $branchId,
                        $menuFk             => $menuId,
                        'number_of_serving' => 0,
                    ]);
                }

                return StockMovement::create([
                    'branch_id'    => $branchId,
                    'source_type'  => $sourceType,
                    'menu_id'      => $menuId,
                    'inventory_id' => $inventory->id,
                    'type'         => $type,
                    'quantity'     => $qty,
                    'balance_after'=> $newBalance,
                    'reason'       => $context['reason'] ?? null,
                    'ref_type'     => $context['ref_type'] ?? null,
                    'ref_id'       => $context['ref_id'] ?? null,
                    'user_id'      => $context['user_id'] ?? auth()->id(),
                    'shift_log_id' => $context['shift_log_id'] ?? null,
                ]);
            }

            if ($type === StockMovement::TYPE_OUT && ($inventory === null || $available < $qty)) {
                throw new InsufficientStockException($sourceType, $menuId, $available, $qty);
            }

            $newBalance = match ($type) {
                StockMovement::TYPE_IN, StockMovement::TYPE_VOID => $available + $qty,
                StockMovement::TYPE_OUT                          => $available - $qty,
                default                                          => $available,
            };

            if ($inventory === null) {
                $inventory = $modelClass::create([
                    'branch_id'         => $branchId,
                    $menuFk             => $menuId,
                    'number_of_serving' => $newBalance,
                ]);
            } else {
                $inventory->number_of_serving = $newBalance;
                $inventory->save();
            }

            return StockMovement::create([
                'branch_id'    => $branchId,
                'source_type'  => $sourceType,
                'menu_id'      => $menuId,
                'inventory_id' => $inventory->id,
                'type'         => $type,
                'quantity'     => $qty,
                'balance_after'=> $newBalance,
                'reason'       => $context['reason'] ?? null,
                'ref_type'     => $context['ref_type'] ?? null,
                'ref_id'       => $context['ref_id'] ?? null,
                'user_id'      => $context['user_id'] ?? auth()->id(),
                'shift_log_id' => $context['shift_log_id'] ?? null,
            ]);
        });
    }
}
