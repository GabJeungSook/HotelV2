<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function shiftLog()
    {
        return $this->belongsTo(ShiftLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function frontdeskMenu()
    {
        return $this->belongsTo(FrontdeskMenu::class);
    }
}
