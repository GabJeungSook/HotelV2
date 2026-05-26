<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_type',
        'menu_id',
        'ingredient_type',
        'ingredient_menu_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function ingredientMenu(): ?Model
    {
        return match ($this->ingredient_type) {
            'kitchen' => Menu::find($this->ingredient_menu_id),
            'pub' => PubMenu::find($this->ingredient_menu_id),
            'frontdesk' => FrontdeskMenu::find($this->ingredient_menu_id),
            default => null,
        };
    }

    public function parentMenu(): ?Model
    {
        return match ($this->menu_type) {
            'kitchen' => Menu::find($this->menu_id),
            'pub' => PubMenu::find($this->menu_id),
            'frontdesk' => FrontdeskMenu::find($this->menu_id),
            default => null,
        };
    }
}
