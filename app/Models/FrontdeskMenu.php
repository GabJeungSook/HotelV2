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

    public function frontdeskInventory()
    {
        return $this->hasOne(FrontdeskInventory::class, 'frontdesk_menu_id');
    }

    /**
     * Alias for frontdeskInventory - used by Food\Inventory component
     */
    public function inventory()
    {
        return $this->hasOne(FrontdeskInventory::class, 'frontdesk_menu_id')
            ->where('branch_id', auth()->user()->branch_id ?? 0);
    }

    protected static function booted(): void
    {
        static::observe(\App\Observers\MenuPriceObserver::class);
    }
}
