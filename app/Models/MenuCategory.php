<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    public function parentCategory()
    {
        return $this->belongsTo(ParentCategory::class);
    }
}
