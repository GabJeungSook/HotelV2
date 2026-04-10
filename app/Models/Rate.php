<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function guest()
    {
        return $this->hasMany(Guest::class, 'rate_id');
    }

    public function stayingHour()
    {
        return $this->belongsTo(StayingHour::class);
    }

    public function transferedGuestReport()
    {
        return $this->hasMany(TransferedGuestReport::class);
    }
}
