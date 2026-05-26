<?php

namespace App\Http\Livewire\Traits;

use App\Models\Menu;
use App\Models\PubMenu;
use App\Models\FrontdeskMenu;
use App\Models\MenuIngredient;

trait ManagesIngredients
{
    public array $ingredients = [];
    public string $ingredientSearch = '';
    public string $ingredientSystemFilter = 'all';

    public function getIngredientSearchResultsProperty(): array
    {
        if (strlen($this->ingredientSearch) < 2) {
            return [];
        }

        $branchId = auth()->user()->branch_id;
        $results = [];

        $systems = match ($this->ingredientSystemFilter) {
            'kitchen' => ['kitchen'],
            'pub' => ['pub'],
            'frontdesk' => ['frontdesk'],
            default => ['kitchen', 'pub', 'frontdesk'],
        };

        if (in_array('kitchen', $systems)) {
            $items = Menu::where('branch_id', $branchId)
                ->where('name', 'like', "%{$this->ingredientSearch}%")
                ->limit(10)
                ->get();
            foreach ($items as $item) {
                $results[] = [
                    'type' => 'kitchen',
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                ];
            }
        }

        if (in_array('pub', $systems)) {
            $items = PubMenu::where('branch_id', $branchId)
                ->where('name', 'like', "%{$this->ingredientSearch}%")
                ->limit(10)
                ->get();
            foreach ($items as $item) {
                $results[] = [
                    'type' => 'pub',
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                ];
            }
        }

        if (in_array('frontdesk', $systems)) {
            $items = FrontdeskMenu::where('branch_id', $branchId)
                ->where('name', 'like', "%{$this->ingredientSearch}%")
                ->limit(10)
                ->get();
            foreach ($items as $item) {
                $results[] = [
                    'type' => 'frontdesk',
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                ];
            }
        }

        return $results;
    }

    public function addIngredient(string $type, int $menuId, string $name): void
    {
        // Prevent duplicates
        foreach ($this->ingredients as $existing) {
            if ($existing['type'] === $type && (int) $existing['menu_id'] === $menuId) {
                return;
            }
        }

        $this->ingredients[] = [
            'type' => $type,
            'menu_id' => $menuId,
            'name' => $name,
            'quantity' => 1,
        ];

        $this->ingredientSearch = '';
    }

    public function removeIngredient(int $index): void
    {
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    protected function saveIngredients(string $menuType, int $menuId): void
    {
        MenuIngredient::where('menu_type', $menuType)
            ->where('menu_id', $menuId)
            ->delete();

        foreach ($this->ingredients as $ing) {
            if ((float) ($ing['quantity'] ?? 0) <= 0) {
                continue;
            }

            MenuIngredient::create([
                'menu_type' => $menuType,
                'menu_id' => $menuId,
                'ingredient_type' => $ing['type'],
                'ingredient_menu_id' => $ing['menu_id'],
                'quantity' => $ing['quantity'],
            ]);
        }
    }

    protected function loadIngredients(string $menuType, int $menuId): void
    {
        $this->ingredients = MenuIngredient::where('menu_type', $menuType)
            ->where('menu_id', $menuId)
            ->get()
            ->map(fn ($ing) => [
                'type' => $ing->ingredient_type,
                'menu_id' => (int) $ing->ingredient_menu_id,
                'name' => $ing->ingredientMenu()?->name ?? 'Unknown',
                'quantity' => (float) $ing->quantity,
            ])->toArray();
    }

    protected function resetIngredients(): void
    {
        $this->ingredients = [];
        $this->ingredientSearch = '';
        $this->ingredientSystemFilter = 'all';
    }
}
