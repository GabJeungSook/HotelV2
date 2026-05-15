<?php

namespace App\Support;

use App\Models\ShiftLog;
use App\Models\User;
use Carbon\Carbon;

class ShiftResolver
{
    public static function current(?User $user = null): string
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return self::fromClock(now());
        }

        $log = ShiftLog::where('frontdesk_id', $user->id)
            ->whereNull('time_out')
            ->latest('time_in')
            ->first();

        return $log->shift ?? $user->shift ?? self::fromClock(now());
    }

    public static function fromClock(Carbon $at): string
    {
        return ($at->hour >= 8 && $at->hour < 20) ? 'AM' : 'PM';
    }

    public static function deriveShiftDate(Carbon $at, string $shift): Carbon
    {
        if ($shift === 'AM' && $at->hour >= 20) {
            return $at->copy()->addDay()->startOfDay();
        }

        if ($shift === 'PM' && $at->hour < 8) {
            return $at->copy()->subDay()->startOfDay();
        }

        return $at->copy()->startOfDay();
    }

    public static function currentShiftDate(?User $user = null): Carbon
    {
        return self::deriveShiftDate(now(), self::current($user));
    }
}
