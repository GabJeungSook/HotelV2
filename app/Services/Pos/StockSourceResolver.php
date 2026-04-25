<?php

namespace App\Services\Pos;

use App\Models\FrontdeskInventory;
use App\Models\Inventory;
use App\Models\PubInventory;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class StockSourceResolver
{
    public function modelFor(string $sourceType): string
    {
        return match ($sourceType) {
            StockMovement::SOURCE_FRONTDESK => FrontdeskInventory::class,
            StockMovement::SOURCE_KITCHEN   => Inventory::class,
            StockMovement::SOURCE_PUB       => PubInventory::class,
            default => throw new InvalidArgumentException("Unknown stock source: {$sourceType}"),
        };
    }

    public function menuForeignKey(string $sourceType): string
    {
        return match ($sourceType) {
            StockMovement::SOURCE_FRONTDESK => 'frontdesk_menu_id',
            StockMovement::SOURCE_KITCHEN   => 'menu_id',
            StockMovement::SOURCE_PUB       => 'pub_menu_id',
            default => throw new InvalidArgumentException("Unknown stock source: {$sourceType}"),
        };
    }

    public function findInventory(string $sourceType, int $menuId, ?int $branchId): ?Model
    {
        $modelClass = $this->modelFor($sourceType);
        $menuFk     = $this->menuForeignKey($sourceType);

        $query = $modelClass::query()->where($menuFk, $menuId);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->first();
    }
}
