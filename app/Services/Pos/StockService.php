<?php

namespace App\Services\Pos;

use App\Models\MenuIngredient;
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

    /**
     * Smart stock-out: deducts the item AND its ingredients (if any).
     * Drop-in replacement for out() at all sale call sites.
     */
    public function smartOut(string $sourceType, int $menuId, float $qty, array $context = []): StockMovement|array
    {
        $hasIngredients = MenuIngredient::where('menu_type', $sourceType)
            ->where('menu_id', $menuId)
            ->exists();

        if ($hasIngredients) {
            return $this->outWithIngredients($sourceType, $menuId, $qty, $context);
        }

        return $this->out($sourceType, $menuId, $qty, $context);
    }

    /**
     * Smart void: reverses the item AND its ingredients (if any).
     * Drop-in replacement for void() at all void call sites.
     */
    public function smartVoid(string $sourceType, int $menuId, float $qty, array $context = []): StockMovement|array
    {
        $hasIngredients = MenuIngredient::where('menu_type', $sourceType)
            ->where('menu_id', $menuId)
            ->exists();

        if ($hasIngredients) {
            return $this->voidWithIngredients($sourceType, $menuId, $qty, $context);
        }

        return $this->void($sourceType, $menuId, $qty, $context);
    }

    /**
     * Deduct stock for a composite item: validates ALL ingredient stock,
     * then deducts the parent AND each ingredient atomically.
     */
    public function outWithIngredients(string $sourceType, int $menuId, float $qty, array $context = []): array
    {
        return DB::transaction(function () use ($sourceType, $menuId, $qty, $context) {
            $ingredients = MenuIngredient::where('menu_type', $sourceType)
                ->where('menu_id', $menuId)
                ->get();

            // Deduct parent item stock (still enforces >= 0)
            $parentMovement = $this->out($sourceType, $menuId, $qty, $context);

            // Deduct each ingredient's stock (allow negative)
            $ingredientMovements = [];
            foreach ($ingredients as $ingredient) {
                $ingredientQty = $ingredient->quantity * $qty;
                $ingredientMovements[] = $this->apply(
                    $ingredient->ingredient_type,
                    (int) $ingredient->ingredient_menu_id,
                    $ingredientQty,
                    StockMovement::TYPE_OUT,
                    array_merge($context, [
                        'ref_type' => 'ingredient_deduction',
                        'reason'   => "Ingredient for {$sourceType}#{$menuId}",
                    ]),
                    allowNegative: true
                );
            }

            return ['parent' => $parentMovement, 'ingredients' => $ingredientMovements];
        });
    }

    /**
     * Reverse stock for a composite item: restores parent AND each ingredient.
     */
    public function voidWithIngredients(string $sourceType, int $menuId, float $qty, array $context = []): array
    {
        return DB::transaction(function () use ($sourceType, $menuId, $qty, $context) {
            $ingredients = MenuIngredient::where('menu_type', $sourceType)
                ->where('menu_id', $menuId)
                ->get();

            $parentMovement = $this->void($sourceType, $menuId, $qty, $context);

            $ingredientMovements = [];
            foreach ($ingredients as $ingredient) {
                $ingredientQty = $ingredient->quantity * $qty;
                $ingredientMovements[] = $this->void(
                    $ingredient->ingredient_type,
                    (int) $ingredient->ingredient_menu_id,
                    $ingredientQty,
                    array_merge($context, [
                        'ref_type' => 'ingredient_void',
                        'reason'   => "Void ingredient for {$sourceType}#{$menuId}",
                    ])
                );
            }

            return ['parent' => $parentMovement, 'ingredients' => $ingredientMovements];
        });
    }

    /**
     * Non-locking pre-check for UI feedback. Returns array of insufficient ingredients.
     * NOT authoritative — the real check happens inside smartOut() with locks.
     */
    public function checkIngredientAvailability(string $sourceType, int $menuId, float $qty, ?int $branchId = null): array
    {
        $branchId = $branchId ?? auth()->user()?->branch_id;
        $insufficient = [];
        $ingredients = MenuIngredient::where('menu_type', $sourceType)
            ->where('menu_id', $menuId)
            ->get();

        foreach ($ingredients as $ing) {
            $needed = $ing->quantity * $qty;
            $inv = $this->resolver->findInventory($ing->ingredient_type, (int) $ing->ingredient_menu_id, $branchId);
            $available = $inv ? (float) $inv->number_of_serving : 0.0;
            if ($available < $needed) {
                $ingredientMenu = $ing->ingredientMenu();
                $insufficient[] = [
                    'ingredient_type'    => $ing->ingredient_type,
                    'ingredient_menu_id' => $ing->ingredient_menu_id,
                    'name'               => $ingredientMenu?->name ?? 'Unknown',
                    'needed'             => $needed,
                    'available'          => $available,
                ];
            }
        }

        return $insufficient;
    }

    private function validateStock(string $sourceType, int $menuId, float $qty, ?int $branchId): void
    {
        $modelClass = $this->resolver->modelFor($sourceType);
        $menuFk = $this->resolver->menuForeignKey($sourceType);

        $inventory = $modelClass::query()
            ->where($menuFk, $menuId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->lockForUpdate()
            ->first();

        $available = $inventory ? (float) $inventory->number_of_serving : 0.0;

        if ($available < $qty) {
            throw new InsufficientStockException($sourceType, $menuId, $available, $qty);
        }
    }

    private function apply(string $sourceType, int $menuId, float $qty, string $type, array $context, bool $allowNegative = false): StockMovement
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

            if (!$allowNegative && $type === StockMovement::TYPE_OUT && ($inventory === null || $available < $qty)) {
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
