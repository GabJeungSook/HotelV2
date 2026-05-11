<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrontdeskCategory extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function frontdesk_menus()
    {
        return $this->hasMany(FrontdeskMenu::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function parentCategory()
    {
        return $this->belongsTo(ParentCategory::class);
    }
}
