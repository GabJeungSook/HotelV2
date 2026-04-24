<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskCurrentBatch extends Model
{
    use HasFactory;

    protected $table = 'kiosk_current_batch';
    protected $guarded = [];

    const STATUS_ACTIVE = 'active';
    const STATUS_PICKED = 'picked';

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
