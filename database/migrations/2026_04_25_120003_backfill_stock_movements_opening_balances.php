<?php

use App\Models\FrontdeskInventory;
use App\Models\Inventory;
use App\Models\PubInventory;
use App\Models\StockMovement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $this->backfillFor(
            FrontdeskInventory::query()->where('number_of_serving', '>', 0)->get(),
            StockMovement::SOURCE_FRONTDESK,
            'frontdesk_menu_id'
        );

        $this->backfillFor(
            Inventory::query()->where('number_of_serving', '>', 0)->get(),
            StockMovement::SOURCE_KITCHEN,
            'menu_id'
        );

        $this->backfillFor(
            PubInventory::query()->where('number_of_serving', '>', 0)->get(),
            StockMovement::SOURCE_PUB,
            'pub_menu_id'
        );
    }

    public function down(): void
    {
        StockMovement::where('type', StockMovement::TYPE_OPENING)->delete();
    }

    private function backfillFor($rows, string $sourceType, string $menuFk): void
    {
        foreach ($rows as $row) {
            $exists = StockMovement::where('source_type', $sourceType)
                ->where('inventory_id', $row->id)
                ->where('type', StockMovement::TYPE_OPENING)
                ->exists();

            if ($exists) {
                continue;
            }

            StockMovement::create([
                'branch_id'    => $row->branch_id,
                'source_type'  => $sourceType,
                'menu_id'      => $row->{$menuFk},
                'inventory_id' => $row->id,
                'type'         => StockMovement::TYPE_OPENING,
                'quantity'     => (float) $row->number_of_serving,
                'balance_after'=> (float) $row->number_of_serving,
                'reason'       => 'opening balance backfill',
            ]);
        }
    }
};
