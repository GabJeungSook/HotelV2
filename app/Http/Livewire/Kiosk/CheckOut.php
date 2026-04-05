<?php

namespace App\Http\Livewire\Kiosk;

use App\Models\Floor;
use App\Models\Room;
use Livewire\Component;
use WireUi\Traits\Actions;

class CheckOut extends Component
{
    use Actions;
    public $steps;
    public $floors = [];
    public $branchId;
    public $floor_id, $room_id;
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
        $this->floors = Floor::where('branch_id', auth()->user()->branch_id)->get();
        $this->steps = 1;
    }
    public function render()
    {
        // $query = Room::where('status', 'Occupied')
        // ->withWhereHas('latestCheckInDetail.guest', function ($q) {
        //     $q->where('has_kiosk_check_out', 1);
        // })
        // ->orderBy('number', 'asc');

        // if (!empty($this->floor_id)) {
        //     $query->where('floor_id', $this->floor_id);
        // }


        return view('livewire.kiosk.check-out', [
            'rooms' => Room::where('branch_id', auth()->user()->branch_id)
                ->where('status', 'Occupied')
                ->when($this->floor_id, function ($query) {
                    return $query->where('floor_id', $this->floor_id);
                })
                ->whereHas('latestCheckInDetail.guest', function ($q) {
                    $q->where('has_kiosk_check_out', 0);
                })
                ->with([
                    'latestCheckInDetail.guest.type'
                ])
                ->orderBy('number', 'asc')
                ->get(),
        ]);
    }

    public function selectRoom($room_id)
    {
        $this->room_id = $room_id;
    }

    public function findRoom()
    {
        $this->validate([
            'room_number' => 'required',
        ], [
            'room_number.required' => 'Please enter your room number.',
        ]);

        $room = Room::where('branch_id', auth()->user()->branch_id)
            ->where('number', $this->room_number)
            ->where('status', 'Occupied')
            ->whereHas('latestCheckInDetail.guest', function ($q) {
                $q->where('has_kiosk_check_out', 0);
            })
            ->first();

        if ($room) {
            $this->room_id = $room->id;
            $this->steps = 2;
        } else {
            $this->dialog()->error(
                $title = 'Room Not Found',
                $description = 'No occupied room found with that number. Please check and try again.'
            );
            $this->room_number = null;
        }
    }

    public function validateQR()
    {
        $this->validate([
            'qr_code' => 'required',
        ]);

        $room = Room::where('id', $this->room_id)
            ->whereHas('latestCheckInDetail.guest', function ($query) {
                $query->where('qr_code', $this->qr_code);
            })
            ->first();
        if ($room) {
            $this->guest = $room->latestCheckInDetail->guest;
            $this->checkInDetail = $room->latestCheckInDetail;
            $this->extension_hours = $this->checkInDetail->extendedGuestReports->sum('total_hours');
            $this->room_amount = $this->checkInDetail->transactions->where('transaction_type_id', 1)->sum('payable_amount');
            $this->total_amount = $this->checkInDetail->transactions->whereNotIn('transaction_type_id', [1,2,5])->sum('payable_amount');
            $this->total_deposit = $this->checkInDetail->transactions()->where('transaction_type_id', 2)->sum('payable_amount');
            $this->steps = 3;
        } else {
             $this->dialog()->error(
                $title = 'Oops!',
                $description = 'Invalid QR code. Please try again.'
            );
            $this->qr_code = null;
        }
    }

    public function confirmCheckOut()
    {
        $this->guest->has_kiosk_check_out = 1;
        $this->guest->save();

        return redirect()->route('kiosk.check-out-success');
    }


      public function backRoom()
    {
        $this->floor_id = null;
    }
}
