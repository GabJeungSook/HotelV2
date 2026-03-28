<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CheckinDetail;

class Room extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function numberWithFormat()
    {
        return 'ROOM #' . $this->number;
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function guest()
    {
        return $this->hasMany(Guest::class);
    }

    // public function latestGuest()
    // {
    //     return $this->hasOne(Guest::class)->latest();
    // }

    public function rates()
    {
        return $this->hasMany(Rate::class);
    }

    public function temporaryCheckInKiosk()
    {
        return $this->hasOne(TemporaryCheckInKiosk::class);
    }

    public function latestGuest()
    {
        return $this->hasOne(Guest::class)->latestOfMany(); // uses created_at by default
    }

    public function latestCheckInDetail()
    {
        return $this->hasOne(CheckinDetail::class)->ofMany([
            'created_at' => 'max',
        ], function ($query) {
            $query->where('is_check_out', false);
        });
    }

    public function checkInDetail()
    {
        return $this->hasOne(CheckinDetail::class);
    }

    public function checkInDetails()
    {
        return $this->hasMany(CheckinDetail::class);
    }

    public function cleaningHistories()
    {
        return $this->hasMany(CleaningHistory::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function extendTransactions()
    {
       //Transactions where transaction_type_id is 3
        return $this->hasMany(Transaction::class)->where('transaction_type_id', 6);
    }

     public function amenitiesTransactions()
    {
       //Transactions where transaction_type_id is 3
        return $this->hasMany(Transaction::class)->where('transaction_type_id', 8);
    }

     public function foodTransactions()
    {
       //Transactions where transaction_type_id is 3
        return $this->hasMany(Transaction::class)->where('transaction_type_id', 9);
    }

    public function damagesTransactions()
    {
       //Transactions where transaction_type_id is 3
        return $this->hasMany(Transaction::class)->where('transaction_type_id', 4);
    }

    public function depositTransactions()
    {
       //Transactions where transaction_type_id is 3
        return $this->hasMany(Transaction::class)->where('transaction_type_id', 2);
    }

    public function depositTransactionsRoomKeyRemote()
    {
       //Transactions where transaction_type_id is 3
        return $this->hasMany(Transaction::class)->where('transaction_type_id', 2)
        ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)');
    }


    public function newGuestReports()
    {
        return $this->hasMany(NewGuestReport::class);
    }
    public function checkOutGuestReports()
    {
        return $this->hasMany(CheckOutGuestReport::class, 'room_id', 'id');
    }
    public function roomBoyReport()
    {
        return $this->hasMany(RoomBoyReport::class);
    }

    public function extendedGuestReports()
    {
        return $this->hasMany(ExtendedGuestReport::class);
    }

    public function temporary_reserved()
    {
        return $this->hasMany(TemporaryReserved::class);
    }

    public function transferedGuestReports()
    {
        return $this->hasMany(TransferedGuestReport::class);
    }
}
