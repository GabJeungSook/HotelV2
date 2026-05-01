<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Floor;
use App\Models\CheckInDetail;
use App\Models\Guest;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;

class OccupiedRoomController extends Controller
{
    public function occupiedRooms($branchId)
    {
        try {
            // Multi-tenant authz: a user can only read occupied rooms in
            // THEIR OWN branch. Without this guard, an authenticated user
            // from branch A could read every guest in branch B by passing
            // its branch_id in the URL.
            if ((int) $branchId !== (int) auth()->user()->branch_id) {
                return ApiResponse::error('Forbidden — branch mismatch', 403);
            }

            $floors = Floor::where('branch_id', $branchId)->with(['rooms' => function ($query) use ($branchId) {
                    $query->where('status', 'Occupied')->with(['latestCheckInDetail.guest.type', 'latestCheckInDetail.transactions', 'latestCheckInDetail.extendedGuestReports']);
                }])
                ->orderBy('number')
                ->get();

            return ApiResponse::success(['data' => $floors], 200);
        } catch (\Exception $e) {
            Log::error('Occupied Rooms API Error: ' . $e->getMessage(), [
                'trace' => $e->getTrace()
            ]);
            return ApiResponse::error($e->getMessage());
        }
    }


}
