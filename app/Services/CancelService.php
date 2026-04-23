<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CheckinDetail;
use App\Models\Guest;
use App\Models\OverrideRequest;
use App\Models\Room;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CancelService
{
    public function completeCancel(OverrideRequest $request, ?int $approvedByUserId = null): array
    {
        $requestData = $request->request_data ?? [];

        // Get the guest and check-in detail
        $guest = Guest::find($request->guest_id);
        $check_in_detail = CheckinDetail::where('guest_id', $guest->id)->first();

        if (!$guest || !$check_in_detail) {
            return ['success' => false, 'message' => 'Guest or check-in details not found.'];
        }

        // Check if guest is still checked in
        if (!$guest->room_id) {
            $request->delete();
            return ['success' => false, 'message' => 'Guest has already been checked out.'];
        }

        DB::beginTransaction();
        try {
            // Update room status to Available
            Room::where('id', $check_in_detail->room_id)->update([
                'status' => 'Available',
            ]);

            // Log activity
            ActivityLog::create([
                'branch_id' => $request->branch_id,
                'user_id' => $approvedByUserId ?? $request->requester_id,
                'activity' => 'Cancel Transaction (Override Approved)',
                'description' => 'Cancelled transaction for guest ' . $guest->name .
                    ' in Room #' . ($request->fromRoom->number ?? 'N/A') .
                    ' - Reason: ' . ($requestData['cancel_reason'] ?? 'N/A'),
            ]);

            // Delete check-in detail
            $check_in_detail->delete();

            // Delete transactions
            Transaction::where('guest_id', $guest->id)->delete();

            // Delete guest
            Guest::where('id', $guest->id)->delete();

            // Mark override request as completed
            $request->update(['status' => 'auto_approved']);

            DB::commit();

            return ['success' => true, 'message' => 'Transaction cancelled successfully.'];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to cancel transaction: ' . $e->getMessage()];
        }
    }
}
