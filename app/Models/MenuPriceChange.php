<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuPriceChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_type',
        'menu_id',
        'field',
        'old_value',
        'new_value',
        'changed_by_user_id',
        'reason',
    ];
}
