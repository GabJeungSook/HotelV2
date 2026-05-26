<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrontdeskMenu extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function frontdeskCategory()
    {
        return $this->belongsTo(FrontdeskCategory::class);
    }

    /**
     * Get inventory for this menu item filtered by current user's branch
     */
    public function frontdeskInventory()
    {
        return $this->hasOne(FrontdeskInventory::class, 'frontdesk_menu_id')
            ->where('branch_id', auth()->user()->branch_id ?? 0);
    }

    /**
     * Alias for frontdeskInventory - used by Food\Inventory component
     */
    public function inventory()
    {
        return $this->hasOne(FrontdeskInventory::class, 'frontdesk_menu_id')
            ->where('branch_id', auth()->user()->branch_id ?? 0);
    }

    public function ingredients()
    {
        return $this->hasMany(MenuIngredient::class, 'menu_id')
            ->where('menu_type', 'frontdesk');
    }

    protected static function booted(): void
    {
        static::observe(\App\Observers\MenuPriceObserver::class);
    }
}
