<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverrideRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'request_data' => 'array',
        'responded_at' => 'datetime',
        'current_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
    ];

    /**
     * The frontdesk user who requested the override
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * The supervisor assigned to handle the request
     */
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * The guest associated with this override request
     */
    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * The check-in detail associated with this override request
     */
    public function checkinDetail()
    {
        return $this->belongsTo(CheckinDetail::class);
    }

    /**
     * The room the guest is transferring from
     */
    public function fromRoom()
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    /**
     * The room the guest is transferring to
     */
    public function toRoom()
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    /**
     * The room type the guest is transferring from
     */
    public function fromType()
    {
        return $this->belongsTo(Type::class, 'from_type_id');
    }

    /**
     * The room type the guest is transferring to
     */
    public function toType()
    {
        return $this->belongsTo(Type::class, 'to_type_id');
    }

    /**
     * The reason for transfer
     */
    public function transferReason()
    {
        return $this->belongsTo(TransferReason::class);
    }

    /**
     * The branch this override request belongs to
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Transactions linked to this override request
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope for pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for declined requests
     */
    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    /**
     * Scope for a specific branch
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope for a specific supervisor
     */
    public function scopeForSupervisor($query, $userId)
    {
        return $query->where('supervisor_id', $userId);
    }

    /**
     * Check if the request is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the request is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the request is declined
     */
    public function isDeclined()
    {
        return $this->status === 'declined';
    }

    /**
     * Approve the override request
     */
    public function approve()
    {
        $this->update([
            'status' => 'approved',
            'responded_at' => now(),
        ]);
    }

    /**
     * Decline the override request with a reason
     */
    public function decline($reason)
    {
        $this->update([
            'status' => 'declined',
            'decline_reason' => $reason,
            'responded_at' => now(),
        ]);
    }

    /**
     * Safely get a value from request_data
     */
    public function getRequestDataValue($key, $default = 'N/A')
    {
        return data_get($this->request_data, $key, $default);
    }

    /**
     * Get guest name (from relation or stored data)
     */
    public function getGuestNameAttribute()
    {
        return $this->guest?->name ?? $this->getRequestDataValue('guest_name');
    }

    /**
     * Get from room number (from relation or stored data)
     */
    public function getFromRoomNumberAttribute()
    {
        return $this->fromRoom?->number ?? $this->getRequestDataValue('from_room_number');
    }

    /**
     * Get to room number (from relation or stored data)
     */
    public function getToRoomNumberAttribute()
    {
        return $this->toRoom?->number ?? $this->getRequestDataValue('to_room_number');
    }

    /**
     * Get requester name (from relation or stored data)
     */
    public function getRequesterNameAttribute()
    {
        return $this->requester?->name ?? $this->getRequestDataValue('requester_name');
    }

    /**
     * Get supervisor name (from relation, with Auto suffix for auto-approved)
     */
    public function getSupervisorNameAttribute()
    {
        $name = $this->supervisor?->name ?? $this->getRequestDataValue('supervisor_name');

        if ($this->status === 'auto_approved') {
            // Show supervisor name with Auto indicator, or just "System (Auto)" if no supervisor
            return $name ? $name . ' (Auto)' : 'System (Auto)';
        }

        return $name ?? '';
    }

    /**
     * Get cancel reason from request_data
     */
    public function getCancelReasonAttribute()
    {
        return $this->getRequestDataValue('cancel_reason');
    }
}
