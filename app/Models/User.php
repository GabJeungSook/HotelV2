<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cash_drawer_id',
        'name',
        'email',
        'password',
        'branch_name',
        'branch_id',
        'roomboy_assigned_floor_id',
        'roomboy_cleaning_room_id',
        'assigned_frontdesks',
        'time_in',
        'shift',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'assigned_frontdesks' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['profile_photo_url'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function cleaningHistories()
    {
        return $this->hasMany(CleaningHistory::class);
    }

    public function floors()
    {
        return $this->belongsToMany(Floor::class);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    public function activity_logs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function cash_drawer()
    {
        return $this->belongsTo(CashDrawer::class);
    }

    public function frontdesk()
    {
        return $this->hasOne(Frontdesk::class);
    }

    public function shiftLogs()
    {
        return $this->hasMany(ShiftLog::class, 'frontdesk_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

     public function remittances()
    {
        return $this->hasMany(Remittance::class);
    }

    /**
     * Override requests made by this user (as frontdesk)
     */
    public function overrideRequestsRequested()
    {
        return $this->hasMany(OverrideRequest::class, 'requester_id');
    }

    /**
     * Override requests assigned to this user (as supervisor)
     */
    public function overrideRequestsAssigned()
    {
        return $this->hasMany(OverrideRequest::class, 'supervisor_id');
    }

    // public function assignedFrontdesks()
    // {
    //     return $this->hasMany(AssignedFrontdesk::class);
    // }
}
