<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function menuCategory()
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'id', 'menu_id');
    }

    public function ingredients()
    {
        return $this->hasMany(MenuIngredient::class, 'menu_id')
            ->where('menu_type', 'kitchen');
    }

    protected static function booted(): void
    {
        static::observe(\App\Observers\MenuPriceObserver::class);
    }
}
