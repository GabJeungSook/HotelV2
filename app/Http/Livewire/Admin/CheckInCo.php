<?php

namespace App\Http\Livewire\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Rate;
use App\Models\Room;
use App\Models\Type;
use App\Models\Guest;
use Livewire\Component;
use App\Models\StayingHour;
use App\Models\Transaction;
use App\Models\CheckinDetail;
use App\Models\NewGuestReport;
use App\Support\ShiftResolver;
use Illuminate\Support\Facades\DB;
use WireUi\Traits\Actions;

class CheckInCo extends Component
{
    use Actions;
    public $name;
    public $contact_number;
    public $type_id;
    public $room_id;
    public $rate_id;
    public $is_longStay;

    public function render()
    {
        return view('livewire.admin.check-in-co', [
            'types' => Type::where(
                'branch_id',
                auth()->user()->branch_id
            )->get(),
            'rooms' => Room::where('branch_id', auth()->user()->branch_id)
                ->where('status', 'Available')
                ->when($this->type_id, function ($query) {
                    $query->where('type_id', $this->type_id);
                })
                ->get(),
            'rates' => Rate::where('branch_id', auth()->user()->branch_id)
                ->when($this->type_id, function ($query) {
                    $query->where('type_id', $this->type_id);
                })
                ->get(),
        ]);
    }

    public function saveCheckInCO()
    {
         $this->validate([
            'name' => 'required',
            'rate_id' => 'required',
            'type_id' => 'required',
            'room_id' => 'required',
        ],
        [
            'name.required' => 'The name field is required.',
            'rate_id' => 'The rate field is required',
            'type_id.required' => 'The room type field is required.',
            'room_id.required' => 'The room field is required.',
        ]);

        // Validation and saving logic for Check-In C/O goes here
        // For long-stay guests, charge 24h-rate × number_of_days (mirrors the
        // kiosk's proceedFillUp logic). Without this multiplier the guest is
        // booked for N days but charged for 1 — silent revenue loss.
        $rate = Rate::where('id', $this->rate_id)->first();
        $room_pay = $this->is_longStay
            ? Rate::where('branch_id', auth()->user()->branch_id)
                  ->where('type_id', $this->type_id)
                  ->max('amount') * (int) $this->is_longStay
            : $rate->amount;
           $transaction = Guest::whereYear(
            'created_at',
            \Carbon\Carbon::today()->year
        )->count();
        $transaction += 1;
        $transaction_code =
            auth()->user()->branch_id .
            today()->format('y') .
            str_pad($transaction, 4, '0', STR_PAD_LEFT);

        DB::beginTransaction();

        // Block check-in if room has unresolved previous guest (2026-04-28)
        $existingCheckin = CheckinDetail::where('room_id', $this->room_id)
            ->where('is_check_out', false)
            ->with('guest:id,name')
            ->first();

        if ($existingCheckin) {
            DB::rollBack();
            $ghostName = $existingCheckin->guest->name ?? 'Unknown';
            $ghostDate = $existingCheckin->check_in_at
                ? Carbon::parse($existingCheckin->check_in_at)->format('M d, Y g:i A')
                : 'unknown date';
            $this->dialog()->error(
                'Room Has Active Guest',
                "Room has unresolved check-in: {$ghostName} (checked in {$ghostDate}). Please checkout the previous guest first."
            );
            return;
        }

        //save guest
             $guest = Guest::create([
                'branch_id' => auth()->user()->branch_id,
                'name' => $this->name,
                'contact' =>
                    $this->contact_number == null
                        ? 'N/A'
                        : $this->contact_number,
                'qr_code' => $transaction_code,
                'room_id' => $this->room_id,
                'rate_id' => $this->rate_id,
                'type_id' => $this->type_id,
                'static_amount' => $room_pay,
                'is_long_stay' => $this->is_longStay != null ? true : false,
                'number_of_days' =>
                    $this->is_longStay != null ? $this->is_longStay : 0,
                'is_co' => true,
            ]);
        //check in guest C/O
        if(auth()->user()->hasRole('frontdesk'))
        {
             $decode_frontdesk = json_decode(
            auth()->user()->assigned_frontdesks,
            true
        );
        }



        $room = Room::where('id', $this->room_id)->first();

        $rate = Rate::where('id', $this->rate_id)->first();

        $staying_hours = StayingHour::where('id', $rate->staying_hour_id)->first()->number;

        $number_of_hours = $staying_hours;
        $next_extension_is_original = false;
        while ($number_of_hours >= auth()->user()->branch->extension_time_reset) {
            $number_of_hours -= auth()->user()->branch->extension_time_reset;
            $next_extension_is_original = true;
        }

        $checkin = CheckinDetail::create([
            'guest_id' => $guest->id,
            'frontdesk_id' => auth()->user()->hasRole('frontdesk') ? $decode_frontdesk[0] : 1,
            'type_id' => $guest->type_id,
            'room_id' => $guest->room_id,
            'rate_id' => $guest->rate_id,
            'static_amount' => $guest->static_amount,
            'hours_stayed' => $guest->is_long_stay
                ? $staying_hours * $guest->number_of_days
                : $staying_hours,
            'total_deposit' => 0,
            'check_in_at' => now(),
            'check_out_at' => $guest->is_long_stay
                ? now()->addDays($guest->number_of_days)
                : now()->addHours($staying_hours),
            'is_long_stay' => $guest->is_long_stay,
            'number_of_hours' => $number_of_hours,
            'next_extension_is_original' => $next_extension_is_original ? 1 : 0,
        ]);
         $room_number = Room::where('id', $guest->room_id)->first()->number;
          if(auth()->user()->hasRole('frontdesk'))
          {
              $assigned_frontdesk = auth()->user()->assigned_frontdesks;
          }
            $shift = ShiftResolver::current();
            

         Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'room_id' => $guest->room_id,
            'guest_id' => $guest->id,
            'floor_id' => $room->floor_id,
            'transaction_type_id' => 1,
            'assigned_frontdesk_id' => auth()->user()->hasRole('frontdesk') ? json_encode($assigned_frontdesk) : json_encode([1, 'Admin']),
            'description' => 'Guest Check In',
            'payable_amount' => $guest->static_amount,
            'paid_amount' => 0,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Guest C/O Checked In at room #' . $room_number,
            'is_co' => true,
            'shift' => $shift,
        ]);

        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'room_id' => $guest->room_id,
            'guest_id' => $guest->id,
            'floor_id' => $room->floor_id,
            'transaction_type_id' => 2,
            'assigned_frontdesk_id' => auth()->user()->hasRole('frontdesk') ? json_encode($assigned_frontdesk) : json_encode([1, 'Admin']),
            'description' => 'Deposit',
            'payable_amount' => 0,
            'paid_amount' => 0,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Deposit From Check In (Room Key & TV Remote)',
            'is_co' => true,
            'shift' => $shift,
        ]);

        $now = Carbon::now();
        $hour = (int) $now->format('H');
        $shift = $now->format('H:i');

        if ($hour >= 8 && $hour < 20) {
            $shift_schedule = 'AM';
            $shift_date = $now->format('F j, Y');
        } else {
            $shift_schedule = 'PM';
            // For times between 00:00 and 07:59 the PM shift started the previous day (8pm)
            $shift_date = $hour < 8 ? $now->copy()->subDay()->format('F j, Y') : $now->format('F j, Y');
        }

        // $shift_date = Carbon::parse(auth()->user()->time_in)->format('F j, Y');
        // $shift = Carbon::parse(auth()->user()->time_in)->format('H:i');
        // $hour = Carbon::parse($shift)->hour;

        // if ($hour >= 8 && $hour < 20) {
        //     $shift_schedule = 'AM';
        // } else {
        //     $shift_schedule = 'PM';
        // }

        NewGuestReport::create([
            'branch_id' => auth()->user()->branch_id,
            'checkin_details_id' => $checkin->id,
            'room_id' => $checkin->room_id,
            'shift_date' => $shift_date,
            'shift' => $shift_schedule,
            'frontdesk_id' => auth()->user()->hasRole('frontdesk') ? $decode_frontdesk[0] : 1,
            'partner_name' => auth()->user()->hasRole('frontdesk') ? $decode_frontdesk[1] : 'Admin',
        ]);

        $room->status = 'Occupied';
        $room->save();
        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'C/O Guest Has been Check-in'
        );
        return redirect()->route('frontdesk.room-monitoring');

    }

    public function redirectBack()
    {
        return redirect()->route('frontdesk.room-monitoring');
    }
}
