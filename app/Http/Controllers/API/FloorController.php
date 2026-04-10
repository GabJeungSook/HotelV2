<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Models\Room;
use App\Models\Floor;
use Illuminate\Http\Request;
use App\Models\TemporaryReserved;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\TemporaryCheckInKiosk;


class FloorController extends Controller
{
    public function index(Request $request, $branchId)
    {
        try{
            $temporaryCheckInKiosk = TemporaryCheckInKiosk::where(
            'branch_id',
            $branchId
            )
            ->get()
            ->pluck('room_id')
            ->toArray();



            $temporaryReserved = TemporaryReserved::where(
            'branch_id',
            $branchId
            )
            ->pluck('room_id')
            ->toArray();

            $typeId = $request->query('type_id');

            $floorId = $request->query('floor_id'); // Optional single floor filter

            $floors = Floor::with(['rooms' => function ($query) use (
            $typeId,
            $temporaryCheckInKiosk,
            $temporaryReserved,
            $floorId
        ) {
            $query->whereIn('status', ['Available', 'Cleaned'])
                ->where('is_priority', true)
                ->when($typeId, fn($q) => $q->where('type_id', $typeId))
                ->when($floorId, fn($q) => $q->where('floor_id', $floorId))
                ->whereNotIn('id', $temporaryCheckInKiosk)
                ->whereNotIn('id', $temporaryReserved)
                ->with(['rates.stayingHour', 'type'])
                ->orderBy('number', 'asc');
        }])
        ->where('branch_id', $branchId)
        ->orderBy('number')
        ->get();





        // Then manually limit rooms in each floor (if needed)
            foreach ($floors as $floor) {
                $floor->setRelation('rooms', $floor->rooms);
            }
            return ApiResponse::success([
                $request,
                'data' => $floors,
            ], 200);
        }catch (\Exception $e) {
            Log::error('API Error: '.$e->getMessage(), [
                'trace' => $e->getTrace()
            ]);
            return ApiResponse::error($e->getMessage());
        }

    }
}
