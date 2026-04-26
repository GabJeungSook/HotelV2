<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'shift_log_id',
        'guest_id',
        'room_id',
        'payment_method',
        'subtotal',
        'discount_amount',
        'discount_reason',
        'total',
        'paid_amount',
        'change_amount',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected $casts = [
        'voided_at' => 'datetime',
    ];

    public function lineItems(): HasMany
    {
        return $this->hasMany(Transaction::class, 'order_id');
    }
}
