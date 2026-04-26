<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_IN      = 'IN';
    public const TYPE_OUT     = 'OUT';
    public const TYPE_ADJUST  = 'ADJUST';
    public const TYPE_VOID    = 'VOID';
    public const TYPE_OPENING = 'OPENING';

    public const SOURCE_FRONTDESK = 'frontdesk';
    public const SOURCE_KITCHEN   = 'kitchen';
    public const SOURCE_PUB       = 'pub';

    protected $fillable = [
        'branch_id',
        'source_type',
        'menu_id',
        'inventory_id',
        'type',
        'quantity',
        'balance_after',
        'reason',
        'ref_type',
        'ref_id',
        'user_id',
        'shift_log_id',
    ];

    protected $casts = [
        'quantity'      => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];
}
