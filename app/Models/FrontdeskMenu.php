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
        return $this->belongsTo(FrontdeskInventory::class,'id', 'frontdesk_menu_id');
    }

    protected static function booted(): void
    {
        static::observe(\App\Observers\MenuPriceObserver::class);
    }
}
