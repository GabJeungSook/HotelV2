<?php

namespace App\Http\Livewire\Kiosk;

use App\Models\CheckinDetail;
use App\Models\Guest;
use Livewire\Component;
use WireUi\Traits\Actions;

class CheckOut extends Component
{
    use Actions;
    public $steps;
    public $room_id;
    public $room_number;
    public $qr_code;
    public $guest;
    public $checkInDetail;
    public $extension_hours;
    public $room_amount;
    public $total_amount;
    public $total_deposit;

    public function mount()
    {
        $this->steps = 1;
    }

    public function render()
    {
        return view('livewire.kiosk.check-out');
    }

    public function validateRoom()
    {
        $this->validate([
            'room_number' => 'required',
        ]);

        // Find active check-in by room number
        $checkInDetail = CheckinDetail::where('is_check_out', false)
            ->whereHas('room', function ($q) {
                $q->where('number', $this->room_number)
                  ->where('status', 'Occupied')
                  ->where('branch_id', auth()->user()->branch_id);
            })
            ->first();

        if (!$checkInDetail) {
            $this->dialog()->error(
                $title = 'Invalid Room Number',
                $description = 'No active check-in found for this room number.'
            );
            $this->room_number = null;
            return;
        }

        $this->steps = 2;
    }

    public function validateQR()
    {
        $this->validate([
            'qr_code' => 'required',
        ]);

        // Find active check-in by QR code and room number
        $checkInDetail = CheckinDetail::where('is_check_out', false)
            ->whereHas('guest', function ($q) {
                $q->where('qr_code', $this->qr_code)
                  ->where('branch_id', auth()->user()->branch_id);
            })
            ->whereHas('room', function ($q) {
                $q->where('number', $this->room_number)
                  ->where('status', 'Occupied');
            })
            ->with(['room', 'guest', 'extendedGuestReports', 'transactions'])
            ->first();

        if (!$checkInDetail) {
            $this->dialog()->error(
                $title = 'Invalid QR Code',
                $description = 'No active check-in found for this QR code.'
            );
            $this->qr_code = null;
            return;
        }

        $guest = $checkInDetail->guest;

        if ($guest->has_kiosk_check_out) {
            $this->dialog()->error(
                $title = 'Already Checked Out',
                $description = 'This guest has already completed kiosk check-out.'
            );
            $this->qr_code = null;
            return;
        }

        $this->guest = $guest;
        $this->checkInDetail = $checkInDetail;
        $this->room_id = $checkInDetail->room_id;
        $this->extension_hours = $checkInDetail->extendedGuestReports->sum('total_hours');
        $this->room_amount = $checkInDetail->transactions->where('transaction_type_id', 1)->sum('payable_amount');
        $this->total_amount = $checkInDetail->transactions->whereNotIn('transaction_type_id', [1, 2, 5])->sum('payable_amount');
        $this->total_deposit = $checkInDetail->transactions()->where('transaction_type_id', 2)->sum('payable_amount');
        $this->steps = 3;
    }

    public function confirmCheckOut()
    {
        $this->guest->has_kiosk_check_out = 1;
        $this->guest->save();

        return redirect()->route('kiosk.check-out-success');
    }

    public function backToRoom()
    {
        $this->room_number = null;
        $this->qr_code = null;
        $this->guest = null;
        $this->checkInDetail = null;
        $this->steps = 1;
    }

    public function backToQR()
    {
        $this->qr_code = null;
        $this->guest = null;
        $this->checkInDetail = null;
        $this->steps = 2;
    }
}
