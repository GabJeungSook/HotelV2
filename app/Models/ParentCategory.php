<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentCategory extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function menuCategories()
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function frontdeskCategories()
    {
        return $this->hasMany(FrontdeskCategory::class);
    }

    public function pubCategories()
    {
        return $this->hasMany(PubCategory::class);
    }
}
