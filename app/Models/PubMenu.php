<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PubMenu extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function pubCategory()
    {
        return $this->belongsTo(PubCategory::class);
    }

    public function pubInventory()
    {
        return $this->belongsTo(PubInventory::class, 'id', 'pub_menu_id');
    }

    public function ingredients()
    {
        return $this->hasMany(MenuIngredient::class, 'menu_id')
            ->where('menu_type', 'pub');
    }

    protected static function booted(): void
    {
        static::observe(\App\Observers\MenuPriceObserver::class);
    }
}
