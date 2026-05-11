<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PubCategory extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function pub_menus()
    {
        return $this->hasMany(PubMenu::class);
    }

    public function parentCategory()
    {
        return $this->belongsTo(ParentCategory::class);
    }
}
