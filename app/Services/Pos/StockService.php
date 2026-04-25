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
