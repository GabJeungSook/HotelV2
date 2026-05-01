<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\Guest;
use App\Models\Room;
use App\Models\TemporaryCheckInKiosk;
use App\Models\TemporaryReserved;
use App\Services\KioskBatchService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckInController extends Controller
{
    /**
     * API kiosk check-in. Mirrors Kiosk\CheckIn::confirmCheckIn safety
     * envelope: serialized room/hold/reservation reads under lockForUpdate,
     * unresolved-previous-guest guard, atomic Guest+TCK creation, batch
     * notification after commit. Two simultaneous API calls (or an API
     * call racing against the web kiosk) now fail-fast on the second one
     * instead of silently double-booking.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        // Multi-tenant authz: a non-superadmin can only act in their own branch.
        if (! $user->hasRole('superadmin') && (int) $request->branch_id !== (int) $user->branch_id) {
            return ApiResponse::error('Unauthorized: branch_id mismatch.', 403);
        }

        $request->validate([
            'branch_id' => 'required|integer',
            'name'      => 'required|string|min:3',
            'room_id'   => 'required|integer',
            'rate_id'   => 'required|integer',
            'type_id'   => 'required|integer',
            'room_pay'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Reject if the room is already Occupied. Matches the web kiosk's
            // shape — locking serializes against any concurrent transition.
            $occupied = Room::where('branch_id', $request->branch_id)
                ->where('id', $request->room_id)
                ->where('status', 'Occupied')
                ->lockForUpdate()
                ->first();

            if ($occupied) {
                DB::rollBack();
                return ApiResponse::error('This room is already occupied.', 409);
            }

            // Reject if a kiosk hold or frontdesk reservation already points
            // at this room. Both lookups locked so concurrent attempts queue.
            $hasKioskHold = TemporaryCheckInKiosk::where('branch_id', $request->branch_id)
                ->where('room_id', $request->room_id)
                ->lockForUpdate()
                ->exists();

            $hasReservation = TemporaryReserved::where('branch_id', $request->branch_id)
                ->where('room_id', $request->room_id)
                ->lockForUpdate()
                ->exists();

            if ($hasKioskHold || $hasReservation) {
                DB::rollBack();
                return ApiResponse::error('Room is already reserved. Please select another room.', 409);
            }

            // Guard against the room having an unresolved previous CheckinDetail
            // (frontdesk forgot to check out, room status drifted). Without
            // this, the new guest would attach a second active CID to the
            // same room and create a "ghost" history entry.
            $openCheckin = CheckinDetail::where('room_id', $request->room_id)
                ->where('is_check_out', false)
                ->lockForUpdate()
                ->first();

            if ($openCheckin) {
                DB::rollBack();
                return ApiResponse::error(
                    'Room has an unresolved previous guest. Please contact the front desk.',
                    409
                );
            }

            // qr_code generation — count this year's guests and increment.
            // The lockForUpdate is the same defense the web kiosk uses to
            // serialize concurrent picks; without it two simultaneous calls
            // both read N and both produce N+1 → duplicate qr_code.
            // Modulo 10000 keeps the suffix to 4 digits per the legacy format.
            $sequence = Guest::whereYear('created_at', Carbon::today()->year)
                ->lockForUpdate()
                ->count();
            $sequence = ($sequence + 1) % 10000;

            $transaction_code = $request->branch_id
                . today()->format('y')
                . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $guest = Guest::create([
                'branch_id'       => $request->branch_id,
                'name'            => $request->name,
                'contact'         => $request->contact == null ? 'N/A' : "09{$request->contact}",
                'qr_code'         => $transaction_code,
                'room_id'         => $request->room_id,
                'rate_id'         => $request->rate_id,
                'type_id'         => $request->type_id,
                'static_amount'   => $request->room_pay,
                'is_long_stay'    => $request->longstay > 0 ? true : false,
                'number_of_days'  => $request->longstay ?? 0,
                'has_discount'    => $request->has_discount,
                'discount_amount' => $request->discount_amount ?? 0,
            ]);

            // Use the branch's configured kiosk timeout, not a hardcoded 20.
            $branch = Branch::where('id', $request->branch_id)->first();
            $kioskTimeLimit = $branch?->kiosk_time_limit ?? 20;

            TemporaryCheckInKiosk::create([
                'guest_id'      => $guest->id,
                'room_id'       => $request->room_id,
                'branch_id'     => $request->branch_id,
                'terminated_at' => Carbon::now()->addMinutes($kioskTimeLimit),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Notify the kiosk batch that this room was picked, same as the
        // web kiosk does. Without this the batch slot stays 'active' and
        // the kiosk keeps offering an already-claimed room.
        KioskBatchService::markPicked((int) $request->branch_id, (int) $request->room_id);

        return response()->json([
            'success' => true,
            'message' => 'Guest successfully checked in.',
            'guest'   => $guest,
        ], 201);
    }
}
