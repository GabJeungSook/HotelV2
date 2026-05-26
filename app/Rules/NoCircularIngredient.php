<?php

namespace App\Rules;

use App\Models\MenuIngredient;
use Illuminate\Contracts\Validation\Rule;

class NoCircularIngredient implements Rule
{
    private string $message = 'Circular ingredient reference detected.';

    public function __construct(
        private string $menuType,
        private int $menuId,
    ) {
    }

    public function passes($attribute, $value): bool
    {
        $ingredientType = $value['ingredient_type'] ?? null;
        $ingredientMenuId = $value['ingredient_menu_id'] ?? null;

        if (!$ingredientType || !$ingredientMenuId) {
            return false;
        }

        // Prevent self-reference
        if ($ingredientType === $this->menuType && (int) $ingredientMenuId === $this->menuId) {
            $this->message = 'A menu item cannot be its own ingredient.';
            return false;
        }

        // Prevent A→B→A circular chain
        $circular = MenuIngredient::where('menu_type', $ingredientType)
            ->where('menu_id', $ingredientMenuId)
            ->where('ingredient_type', $this->menuType)
            ->where('ingredient_menu_id', $this->menuId)
            ->exists();

        if ($circular) {
            $this->message = 'Circular ingredient reference detected.';
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->message;
    }
}
