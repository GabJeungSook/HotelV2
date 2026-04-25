<?php

namespace App\Observers;

use App\Models\FrontdeskMenu;
use App\Models\Menu;
use App\Models\MenuPriceChange;
use App\Models\PubMenu;
use Illuminate\Database\Eloquent\Model;

class MenuPriceObserver
{
    public function updating(Model $menu): void
    {
        $sourceType = $this->sourceTypeFor($menu);
        if ($sourceType === null) {
            return;
        }

        foreach (['price', 'name'] as $field) {
            if (! $menu->isDirty($field)) {
                continue;
            }

            try {
                MenuPriceChange::create([
                    'source_type'        => $sourceType,
                    'menu_id'            => $menu->id,
                    'field'              => $field,
                    'old_value'          => (string) $menu->getOriginal($field),
                    'new_value'          => (string) $menu->{$field},
                    'changed_by_user_id' => auth()->id(),
                    'reason'             => null,
                ]);
            } catch (\Throwable $e) {
                // Audit failure must NEVER block a legitimate menu update.
                \Log::warning('MenuPriceObserver write failed', [
                    'source_type' => $sourceType,
                    'menu_id'     => $menu->id,
                    'field'       => $field,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    private function sourceTypeFor(Model $menu): ?string
    {
        return match (get_class($menu)) {
            FrontdeskMenu::class => 'frontdesk',
            Menu::class          => 'kitchen',
            PubMenu::class       => 'pub',
            default              => null,
        };
    }
}
