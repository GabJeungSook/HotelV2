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

        // Store guest/room info for historical reference BEFORE deletions
        $guestName = $guest->name;
        $roomNumber = $request->fromRoom->number ?? null;
        $requesterName = $request->requester->name ?? null;

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
                'description' => 'Cancelled transaction for guest ' . $guestName .
                    ' in Room #' . ($roomNumber ?? 'N/A') .
                    ' - Reason: ' . ($requestData['cancel_reason'] ?? 'N/A'),
            ]);

            // Delete check-in detail
            $check_in_detail->delete();

            // Delete transactions
            Transaction::where('guest_id', $guest->id)->delete();

            // Delete guest
            Guest::where('id', $guest->id)->delete();

            // Mark override request as completed
            // If supervisor_id is set, it was manually approved; otherwise it was auto-approved
            $status = $request->supervisor_id ? 'approved' : 'auto_approved';

            // Save historical data in request_data for reports
            $historicalData = array_merge($requestData, [
                'guest_name' => $guestName,
                'from_room_number' => $roomNumber,
                'requester_name' => $requesterName,
            ]);

            $request->update([
                'status' => $status,
                'request_data' => $historicalData,
            ]);

            DB::commit();

            return ['success' => true, 'message' => 'Transaction cancelled successfully.'];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to cancel transaction: ' . $e->getMessage()];
        }
    }
}
