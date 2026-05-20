<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckinDetail extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function rate()
    {
        return $this->belongsTo(Rate::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function latestGuest()
    {
        return $this->belongsTo(Guest::class, 'guest_id')->latest();
    }

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function newGuestReports()
    {
        return $this->hasMany(NewGuestReport::class);
    }

    public function checkOutGuestReports()
    {
        return $this->hasMany(CheckOutGuestReport::class, 'checkin_details_id');
    }

    public function roomBoyReport()
    {
        return $this->hasMany(RoomBoyReport::class);
    }

    public function extendedGuestReports()
    {
        return $this->hasMany(ExtendedGuestReport::class, 'checkin_details_id');
    }

    public function frontdesk()
    {
        return $this->belongsTo(Frontdesk::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'checkin_detail_id');
    }

    public function checkinTransaction()
    {
        return $this->hasOne(Transaction::class, 'checkin_detail_id')
            ->where('transaction_type_id', 1);
    }

    public function transferedGuestReports()
    {
        return $this->hasMany(TransferedGuestReport::class);
    }
}
