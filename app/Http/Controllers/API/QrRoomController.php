<?php

namespace App\Http\Controllers\Api;

use App\Models\Room;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;

class QrRoomController extends Controller
{
    public function getRoomByQr($qrCode, Request $request)
    {
        try {
            // Multi-tenant authz: a user can only resolve a QR within their
            // own branch. Without this guard, an authenticated user from
            // branch A could read another branch's guest data by passing
            // its branch_id and a known qr_code.
            if ((int) $request->branch_id !== (int) auth()->user()->branch_id) {
                return ApiResponse::error('Forbidden — branch mismatch', 403);
            }

            $room = Room::where('status', 'Occupied')
                ->whereHas('checkInDetail.guest', function ($query) use ($qrCode, $request) {
                    $query->where('branch_id', $request->branch_id)->where('qr_code', $qrCode);
                })
                ->with([
                    'latestCheckInDetail.guest.type',
                    'floor', // Optional: Include floor info if needed
                    'latestCheckInDetail.transactions'
                ])
                ->first();

            if (!$room) {
                return ApiResponse::error('Room not found for this QR code', 404);
            }

            return ApiResponse::success(['data' => $room], 200);
        } catch (\Exception $e) {
            Log::error('QR Room Lookup Error: ' . $e->getMessage());
            return ApiResponse::error($e->getMessage());
        }
    }
}
