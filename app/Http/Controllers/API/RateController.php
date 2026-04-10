<?php

namespace App\Http\Controllers\API;

use App\Models\Rate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RateController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer',
            'room_id' => 'required|integer',
        ]);

        $rates = Rate::where('branch_id', $request->branch_id)
                ->where('room_id', $request->room_id)
                ->with('stayingHour')
                ->get();

        // Add staying hour data to each rate
        $rates = $rates->map(function ($rate) {
            $rateArray = $rate->toArray();
            $rateArray['staying_hour'] = $rate->stayingHour;
            return $rateArray;
        });

        return response()->json([
            'success' => true,
            'data' => $rates
        ]);
    }
}
