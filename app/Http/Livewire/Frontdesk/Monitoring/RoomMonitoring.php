<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use Livewire\Component;
use DB;
use App\Models\Floor;
use App\Models\TemporaryCheckInKiosk;
use App\Models\TemporaryReserved;
use App\Models\CheckinDetail;
use App\Models\NewGuestReport;
use App\Models\Transaction;
use App\Models\Room;
use App\Models\Guest;
use App\Models\Rate;
use App\Models\Type;
use App\Models\StayingHour;
use App\Models\AssignedFrontdesk;
use WireUi\Traits\Actions;
use Livewire\WithPagination;
use Carbon\Carbon;

class RoomMonitoring extends Component
{
    use WithPagination;
    use Actions;
    public $search, $search_kiosk, $search_reserve;
    public $filter_floor, $filter_status;
    public $checkInModal = false;
    public $checkInReserveModal = false;
    public $guest_details_modal = false;
    public $guestCheckInModal = false;
    public $guest_details;
    public $temporary_checkIn,
        $guest,
        $room,
        $rate,
        $stayingHour,
        $additional_charges;
    public $temporary_reserve,
        $guest_reserve,
        $room_reserve,
        $rate_reserve,
        $stayingHour_reserve,
        $additional_charges_reserve;
    public $total, $amountPaid, $excess_amount;
    public $total_reserve, $amountPaid_reserve, $excess_amount_reserve;
    public $save_excess;
    public $save_excess_reserve;
    public $excess = false;
    public $excess_reserve = false;
    public $reserve_div = false;

    public $type_id;
    public $room_id;
    public $rate_id;
    public $is_longStay;
    public $number_of_days;
    public $name;
    public $contact_number;

    public $listener_identifier;
    public $checkInDetails = [];
    public function getListeners()
    {
        return [
             "echo-private:newcheckin.auth()->user()->branch_id,CheckInEvent" => 'searchKiosk',
            'echo-private:newcheckin.' .
            auth()->user()->branch_id .
            ',CheckInEvent' => 'searchKiosk',
        ];
    }
    public function mount()
    {
        $this->listener_identifier = auth()->user()->branch_id;
        $this->floors = Floor::where('branch_id', auth()->user()->branch_id)
            ->orderBy('number', 'asc')
            ->get();
    }

    public function render()
    {
        return view('livewire.frontdesk.monitoring.room-monitoring', [
             'rooms' => $this->searchRooms(),
            'kiosks' => $this->searchKiosk(),
            'types' => Type::where(
                'branch_id',
                auth()->user()->branch_id
            )->get(),
            // 'rooms' => Room::where('branch_id', auth()->user()->branch_id)
            //     ->where('status', 'Available')
            //     ->when($this->type_id, function ($query) {
            //         $query->where('type_id', $this->type_id);
            //     })
            //     ->get(),
            'ratess' => Rate::where('branch_id', auth()->user()->branch_id)
                ->when($this->type_id, function ($query) {
                    $query->where('type_id', $this->type_id);
                })
                ->get(),
            'roomss' => Room::where('branch_id', auth()->user()->branch_id)
                ->where('status', 'Available')
                ->when($this->type_id, function ($query) {
                    $query->where('type_id', $this->type_id);
                })
                ->get(),
                'floors' => Floor::where('branch_id', auth()->user()->branch_id)
                ->orderBy('number', 'asc')
                ->get()
        ]);
    }

    public function updatedIsLongStay()
    {
        if ($this->is_longStay == true) {
            $this->rate_id = null;
        } else {
            $this->number_of_days = null;
        }
    }

    public function checkInGuest()
    {
        $transaction = Guest::whereYear(
            'created_at',
            \Carbon\Carbon::today()->year
        )->count();
        $transaction += 1;
        $transaction_code =
            auth()->user()->branch_id .
            today()->format('y') .
            str_pad($transaction, 4, '0', STR_PAD_LEFT);
        if ($this->is_longStay == true) {
            dd('true');
        } else {
            $this->validate([
                'name' => 'required',
                'type_id' => 'required',
                'room_id' => 'required',
                'rate_id' => 'required',
            ]);
            $this->checkInDetails = [
                'transaction_code' => $transaction_code,
                'guest_name' => $this->name,
                'guest_contact_number' => $this->contact_number,
                'room_id' => $this->room_id,
                'room' => Room::where('id', $this->room_id)
                    ->first()
                    ->numberWithFormat(),
                'type_id' => $this->type_id,
                'rate_id' => $this->rate_id,
                'rate' => Rate::where('id', $this->rate_id)->first()
                    ->stayingHour->number,
                'room_rate' => Rate::where('id', $this->rate_id)->first()
                    ->amount,
            ];
            $this->guestCheckInModal = true;
        }
    }

    public function searchKiosk()
    {
        // ---->

        return TemporaryCheckInKiosk::with('guest')
            ->where('branch_id', auth()->user()->branch_id)
            ->where(function ($query) {
                $query->whereHas('guest', function ($query) {
                    $query
                        ->where('name', 'like', '%' . $this->search_kiosk . '%')
                        ->orWhere(
                            'qr_code',
                            'like',
                            '%' . $this->search_kiosk . '%'
                        );
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function searchReserves()
    {
        // ---->

        return TemporaryReserved::with('guest')
            ->where('branch_id', auth()->user()->branch_id)
            ->where(function ($query) {
                $query->whereHas('guest', function ($query) {
                    $query
                        ->where(
                            'name',
                            'like',
                            '%' . $this->search_reserve . '%'
                        )
                        ->orWhere(
                            'qr_code',
                            'like',
                            '%' . $this->search_reserve . '%'
                        );
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // public function searchRooms()
    // {
    //     return Room::where('branch_id', auth()->user()->branch_id)

    //     ->when($this->filter_status, function ($query) {
    //         return $query->where('status', $this->filter_status);
    //     })
    //     ->when($this->filter_floor, function ($query) {
    //         return $query->where('floor_id', $this->filter_floor);
    //     })
    //     ->when($this->search, function ($query) {
    //         return $query->where('number', 'like', '%' . $this->search . '%');
    //     })
    //     ->with('floor')
    //     ->with(['checkInDetails' => function ($query) {
    //         $query->orderBy('check_out_at', 'asc');
    //     }])
    //     ->selectRaw('rooms.*, COALESCE(checkin_details.check_out_at, NULL) AS check_out_at') // Add check_out_at to select clause
    //     ->leftJoin('checkin_details', function ($join) {
    //         $join->on('rooms.id', '=', 'checkin_details.room_id');
    //     }) // Join checkInDetails
    //     ->orderByRaw('(CASE WHEN check_out_at IS NULL THEN 1 ELSE 0 END), check_out_at ASC') // Use the selected check_out_at
    //     ->paginate(10);
    // }

    public function searchRooms()
    {
        $branchId = auth()->user()->branch_id;

        $latestCheckOutSubquery = \DB::table('checkin_details as cid')
            ->select('cid.room_id', \DB::raw('MAX(cid.check_out_at) as latest_check_out_at'))
            ->groupBy('cid.room_id');

        return Room::where('branch_id', $branchId)
            ->when($this->filter_status, function ($query) {
                return $query->where('status', $this->filter_status);
            })
            ->when($this->filter_floor, function ($query) {
                return $query->where('floor_id', $this->filter_floor);
            })
            ->when($this->search, function ($query) {
                return $query->where('number', 'like', '%' . $this->search . '%');
            })
            ->leftJoinSub($latestCheckOutSubquery, 'latest_check_out', function ($join) {
                $join->on('rooms.id', '=', 'latest_check_out.room_id');
            })
            ->with('floor')
            ->with(['checkInDetails' => function ($query) {
                $query->orderBy('check_out_at', 'asc');
            }])
            ->select('rooms.*', 'latest_check_out.latest_check_out_at as check_out_at')
            ->orderByRaw('(CASE WHEN latest_check_out.latest_check_out_at IS NULL THEN 1 ELSE 0 END), latest_check_out.latest_check_out_at ASC')
            ->paginate(10);
    }

    public function viewDetails($id)
    {
        $this->guest_details = Guest::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $id)
            ->first();
        $this->guest_details_modal = true;
    }

    public function checkIn($id)
    {
            $this->additional_charges = 200;
            $this->excess_amount = 0;
            $this->temporary_checkIn = TemporaryCheckInKiosk::where(
                'branch_id',
                auth()->user()->branch_id
            )
                ->where('id', $id)
                ->first();
            $this->guest = Guest::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->temporary_checkIn->guest_id)
                ->first();
            $this->room = Room::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->temporary_checkIn->room_id)
                ->first();
            $this->rate = Rate::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->guest->rate_id)
                ->first();
            $this->stayingHour = StayingHour::where(
                'branch_id',
                auth()->user()->branch_id
            )
                ->where('id', $this->rate->staying_hour_id)
                ->first();
            $this->total = $this->guest->static_amount + $this->additional_charges;
            return $this->checkInModal = true;

    }

    public function checkInReserve($id)
    {
        $this->additional_charges_reserve = 200;
        $this->excess_amount_reserve = 0;
        $this->temporary_reserve = TemporaryReserved::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('room_id', $id)
            ->first();
        $this->guest_reserve = Guest::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $this->temporary_reserve->guest_id)
            ->first();
        $this->room_reserve = Room::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $this->temporary_reserve->room_id)
            ->first();
        $this->rate_reserve = Rate::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $this->guest_reserve->rate_id)
            ->first();
        $this->stayingHour_reserve = StayingHour::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $this->rate_reserve->staying_hour_id)
            ->first();
        $this->total_reserve =
            $this->guest_reserve->static_amount +
            $this->additional_charges_reserve;
        return $this->checkInReserveModal = true;
    }

    public function updatedAmountPaid()
    {
        if ($this->amountPaid > $this->total) {
            $this->excess = true;
            $this->excess_amount = $this->amountPaid - $this->total;
        } else {
            $this->excess = false;
            $this->excess_amount = 0;
        }
    }

    public function updatedRateId()
    {
        $this->total = Rate::where('id', $this->rate_id)->first()->amount + 200;
    }

    public function updatedAmountPaidReserve()
    {
        if ($this->amountPaid_reserve > $this->total_reserve) {
            $this->reserve_div = true;
            $this->excess_amount_reserve =
                $this->amountPaid_reserve - $this->total_reserve;
        } else {
            $this->excess_reserve = false;
            $this->excess_amount_reserve = 0;
        }
    }

    public function storeGuest()
    {
        $this->validate([
            'amountPaid' => 'required|gte:' . $this->total,
        ]);
        DB::beginTransaction();
        $guest = Guest::create([
            'branch_id' => auth()->user()->branch_id,
            'name' => $this->name,
            'contact' =>
                $this->contact_number == null ? 'N/A' : $this->contact_number,
            'qr_code' => $this->checkInDetails['transaction_code'],
            'room_id' => $this->room_id,
            'rate_id' => $this->rate_id,
            'type_id' => $this->type_id,
            'static_amount' => $this->total,
            'is_long_stay' => $this->is_longStay != null ? true : false,
            'number_of_days' =>
                $this->is_longStay != null ? $this->is_longStay : 0,
        ]);
        $checkin = CheckinDetail::create([
            'guest_id' => $guest->id,
            'type_id' => $this->type_id,
            'room_id' => $this->room_id,
            'rate_id' => $this->rate_id,
            'static_amount' => $guest->static_amount,
            'hours_stayed' => $this->is_longStay
                ? 0
                : $this->checkInDetails['rate'],
            'total_deposit' => $this->save_excess
                ? $this->excess_amount + $this->additional_charges
                : $this->additional_charges,
            'check_in_at' => now(),
            'check_out_at' => $guest->is_long_stay
                ? now()->addDays($guest->number_of_days)
                : now()->addHours($this->checkInDetails['rate']),
            'is_long_stay' => $this->is_longStay != null ? true : false,
        ]);
        $room_number = Room::where('id', $this->room_id)->first()->number;
        $assigned_frontdesk = auth()->user()->assigned_frontdesks;
        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'room_id' => $this->room_id,
            'guest_id' => $guest->id,
            'floor_id' => Room::where('id', $this->room_id)->first()->floor->id,
            'transaction_type_id' => 1,
            'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
            'description' => 'Guest Check In',
            'payable_amount' => $guest->static_amount,
            'paid_amount' => $this->amountPaid,
            'change_amount' =>
                $this->excess_amount != 0 ? $this->excess_amount : 0,
            'deposit_amount' => 0,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Guest Checked In at room #' . $room_number,
        ]);

        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'room_id' => $guest->room_id,
            'guest_id' => $guest->id,
            'floor_id' => Room::where('id', $this->room_id)->first()->floor->id,
            'transaction_type_id' => 2,
            'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
            'description' => 'Deposit',
            'payable_amount' => 200,
            'paid_amount' => $this->amountPaid,
            'change_amount' =>
                $this->excess_amount != 0 ? $this->excess_amount : 0,
            'deposit_amount' => 200,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Deposit From Check In (Room Key & TV Remote)',
        ]);

        if ($this->save_excess) {
            Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'room_id' => $guest->room_id,
                'guest_id' => $guest->id,
                'floor_id' => Room::where('id', $this->room_id)->first()->floor
                    ->id,
                'transaction_type_id' => 2,
                'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
                'description' => 'Deposit',
                'payable_amount' => $this->excess_amount,
                'paid_amount' => $this->amountPaid,
                'change_amount' => 0,
                'deposit_amount' => $this->excess_amount,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' => 'Deposit From Check In (Excess Amount)',
            ]);
        }
        $this->reset(['amountPaid']);
        $this->guestCheckInModal = false;
        Room::where('id', $this->room_id)
            ->first()
            ->update([
                'status' => 'Occupied',
            ]);

        DB::commit();
        $this->reset();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Guest Has been Check-in'
        );
    }

    public function saveCheckInDetails()
    {
        $this->validate([
            'amountPaid' => 'required|gte:' . $this->total,
        ]);

        DB::beginTransaction();
        $checkin = CheckinDetail::create([
            'guest_id' => $this->guest->id,
            'type_id' => $this->guest->type_id,
            'room_id' => $this->guest->room_id,
            'rate_id' => $this->guest->rate_id,
            'static_amount' => $this->guest->static_amount,
            'hours_stayed' => $this->temporary_checkIn->guest->is_long_stay
                ? $this->stayingHour->number *
                    $this->temporary_checkIn->guest->number_of_days
                : $this->stayingHour->number,
            'total_deposit' => $this->save_excess
                ? $this->excess_amount + $this->additional_charges
                : $this->additional_charges,
            'check_in_at' => now(),
            'check_out_at' => $this->guest->is_long_stay
                ? now()->addDays($this->guest->number_of_days)
                : now()->addHours($this->stayingHour->number),
            'is_long_stay' => $this->temporary_checkIn->guest->is_long_stay,
            'number_of_hours' =>
                auth()->user()->branch->extension_time_reset -
                ($this->temporary_checkIn->guest->is_long_stay
                    ? $this->stayingHour->number *
                        $this->temporary_checkIn->guest->number_of_days
                    : $this->stayingHour->number),
        ]);
        $room_number = Room::where('id', $this->guest->room_id)->first()
            ->number;
        $assigned_frontdesk = auth()->user()->assigned_frontdesks;
        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'room_id' => $this->guest->room_id,
            'guest_id' => $this->guest->id,
            'floor_id' => $this->room->floor_id,
            'transaction_type_id' => 1,
            'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
            'description' => 'Guest Check In',
            'payable_amount' => $this->guest->static_amount,
            'paid_amount' => $this->amountPaid,
            'change_amount' =>
                $this->excess_amount != 0 ? $this->excess_amount : 0,
            'deposit_amount' => 0,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Guest Checked In at room #' . $room_number,
        ]);

        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'room_id' => $this->guest->room_id,
            'guest_id' => $this->guest->id,
            'floor_id' => $this->room->floor_id,
            'transaction_type_id' => 2,
            'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
            'description' => 'Deposit',
            'payable_amount' => $this->additional_charges,
            'paid_amount' => $this->amountPaid,
            'change_amount' =>
                $this->excess_amount != 0 ? $this->excess_amount : 0,
            'deposit_amount' => $this->additional_charges,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Deposit From Check In (Room Key & TV Remote)',
        ]);

        if ($this->save_excess) {
            Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'room_id' => $this->guest->room_id,
                'guest_id' => $this->guest->id,
                'floor_id' => $this->room->floor_id,
                'transaction_type_id' => 2,
                'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
                'description' => 'Deposit',
                'payable_amount' => $this->excess_amount,
                'paid_amount' => $this->amountPaid,
                'change_amount' => 0,
                'deposit_amount' => $this->excess_amount,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' => 'Deposit From Check In (Excess Amount)',
            ]);
        }
        $shift_date = Carbon::parse(auth()->user()->time_in)->format('F j, Y');
        $shift = Carbon::parse(auth()->user()->time_in)->format('H:i');
        $hour = Carbon::parse($shift)->hour;

        if ($hour >= 8 && $hour < 20) {
            $shift_schedule = 'AM';
        } else {
            $shift_schedule = 'PM';
        }

        $decode_frontdesk = json_decode(
            auth()->user()->assigned_frontdesks,
            true
        );
        NewGuestReport::create([
            'branch_id' => auth()->user()->branch_id,
            'checkin_details_id' => $checkin->id,
            'room_id' => $checkin->room_id,
            'shift_date' => $shift_date,
            'shift' => $shift_schedule,
            'frontdesk_id' => $decode_frontdesk[0],
            'partner_name' => $decode_frontdesk[1],
        ]);

        $this->reset(['amountPaid']);
        $this->checkInModal = false;
        Room::where('id', $this->temporary_checkIn->room_id)
            ->first()
            ->update([
                'status' => 'Occupied',
            ]);
        TemporaryCheckInKiosk::where('id', $this->temporary_checkIn->id)
            ->first()
            ->delete();
        $this->temporary_checkIn = null;
        DB::commit();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Guest Has been Check-in'
        );
    }

    public function saveReserveCheckInDetails()
    {
        $this->validate([
            'amountPaid_reserve' => 'required|gte:' . $this->total_reserve,
        ]);

        DB::beginTransaction();
        $checkin = CheckinDetail::create([
            'guest_id' => $this->guest_reserve->id,
            'type_id' => $this->guest_reserve->type_id,
            'room_id' => $this->guest_reserve->room_id,
            'rate_id' => $this->guest_reserve->rate_id,
            'static_amount' => $this->guest_reserve->static_amount,
            'hours_stayed' => $this->temporary_reserve->guest->is_long_stay
                ? $this->stayingHour_reserve->number *
                    $this->temporary_reserve->guest->number_of_days
                : $this->stayingHour_reserve->number,
            'total_deposit' => $this->save_excess_reserve
                ? $this->excess_amount_reserve +
                    $this->additional_charges_reserve
                : $this->additional_charges_reserve,
            'check_in_at' => now(),
            'check_out_at' => $this->guest_reserve->is_long_stay
                ? now()->addDays($this->guest_reserve->number_of_days)
                : now()->addHours($this->stayingHour_reserve->number),
            'is_long_stay' => $this->temporary_reserve->guest->is_long_stay,
            'number_of_hours' =>
                auth()->user()->branch->extension_time_reset -
                ($this->temporary_reserve->guest->is_long_stay
                    ? $this->stayingHour_reserve->number *
                        $this->temporary_reserve->guest->number_of_days
                    : $this->stayingHour_reserve->number),
        ]);
        $room_number = Room::where('id', $this->guest_reserve->room_id)->first()
            ->number;
        $assigned_frontdesk = auth()->user()->assigned_frontdesks;
        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'room_id' => $this->guest_reserve->room_id,
            'guest_id' => $this->guest_reserve->id,
            'floor_id' => $this->room_reserve->floor_id,
            'transaction_type_id' => 1,
            'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
            'description' => 'Guest Check In',
            'payable_amount' => $this->guest_reserve->static_amount,
            'paid_amount' => $this->amountPaid_reserve,
            'change_amount' =>
                $this->excess_amount_reserve != 0
                    ? $this->excess_amount_reserve
                    : 0,
            'deposit_amount' => 0,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Guest Checked In at room #' . $room_number,
        ]);

        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'room_id' => $this->guest_reserve->room_id,
            'guest_id' => $this->guest_reserve->id,
            'floor_id' => $this->room_reserve->floor_id,
            'transaction_type_id' => 2,
            'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
            'description' => 'Deposit',
            'payable_amount' => $this->additional_charges_reserve,
            'paid_amount' => $this->amountPaid_reserve,
            'change_amount' =>
                $this->excess_amount_reserve != 0
                    ? $this->excess_amount_reserve
                    : 0,
            'deposit_amount' => $this->additional_charges_reserve,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Deposit From Check In (Room Key & TV Remote)',
        ]);

        if ($this->save_excess_reserve) {
            Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'room_id' => $this->guest_reserve->room_id,
                'guest_id' => $this->guest_reserve->id,
                'floor_id' => $this->room_reserve->floor_id,
                'transaction_type_id' => 2,
                'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
                'description' => 'Deposit',
                'payable_amount' => $this->excess_amount_reserve,
                'paid_amount' => $this->amountPaid_reserve,
                'change_amount' => 0,
                'deposit_amount' => $this->excess_amount_reserve,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' => 'Deposit From Check In (Excess Amount)',
            ]);
        }
        $shift_date = Carbon::parse(auth()->user()->time_in)->format('F j, Y');
        $shift = Carbon::parse(auth()->user()->time_in)->format('H:i');
        $hour = Carbon::parse($shift)->hour;

        if ($hour >= 8 && $hour < 20) {
            $shift_schedule = 'AM';
        } else {
            $shift_schedule = 'PM';
        }

        $decode_frontdesk = json_decode(
            auth()->user()->assigned_frontdesks,
            true
        );
        NewGuestReport::create([
            'branch_id' => auth()->user()->branch_id,
            'checkin_details_id' => $checkin->id,
            'room_id' => $checkin->room_id,
            'shift_date' => $shift_date,
            'shift' => $shift_schedule,
            'frontdesk_id' => $decode_frontdesk[0],
            'partner_name' => $decode_frontdesk[1],
        ]);

        $this->reset(['amountPaid']);
        $this->checkInReserveModal = false;
        Room::where('id', $this->temporary_reserve->room_id)
            ->first()
            ->update([
                'status' => 'Occupied',
            ]);
        TemporaryReserved::where('id', $this->temporary_reserve->id)
            ->first()
            ->delete();
        $this->temporary_reserve = null;
        DB::commit();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Guest Has been Check-in'
        );
    }
}
