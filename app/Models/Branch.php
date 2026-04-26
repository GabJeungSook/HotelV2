<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'force_auto_override' => 'boolean',
        'pos_v2_enabled' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function types()
    {
        return $this->hasMany(Type::class);
    }

    public function temporary_reserved()
    {
        return $this->hasMany(TemporaryReserve::class);
    }

    public function activity_logs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function overrideRequests()
    {
        return $this->hasMany(OverrideRequest::class);
    }
}
