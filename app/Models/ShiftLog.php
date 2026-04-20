<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftLog extends Model
{
    protected $guarded = [];
        protected $casts = [
        'time_in'  => 'datetime',
        'time_out' => 'datetime',
    ];

    use HasFactory;

    public function frontdesk()
    {
        return $this->belongsTo(User::class, 'frontdesk_id');
    }

    public function cash_drawer()
    {
        return $this->belongsTo(CashDrawer::class, 'cash_drawer_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function remittances()
    {
        return $this->hasMany(Remittance::class);
    }

    public function posTransactions()
    {
        return $this->hasMany(PosTransaction::class);
    }

    public function paymentOnShorts()
    {
        return $this->hasMany(PaymentOnShort::class);
    }
}
