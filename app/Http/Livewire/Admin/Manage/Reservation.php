<?php

namespace App\Http\Livewire\Admin\Manage;

use Livewire\Component;
use Filament\Tables;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use App\Models\TemporaryReserved;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Columns\TextColumn;
use App\Models\Type;
use App\Models\Rate;
use App\Models\Room;
use App\Models\Guest;
use DB;
use WireUi\Traits\Actions;
class Reservation extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;
    use Actions;
    public $add_modal = false;
    public $type_id;
    public $room_id;
    public $rate_id;
    public $is_longStay;
    public $number_of_days;
    public $name;
    public $contact_number;

    protected function getTableQuery(): Builder
    {
        return TemporaryReserved::query()->where(
            'branch_id',
            auth()->user()->branch_id
        );
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('guest.qr_code')
                ->label('QR CODE')
                ->searchable()
                ->sortable(),
            TextColumn::make('guest.name')
                ->label('GUEST NAME')
                ->searchable()
                ->sortable(),
            TextColumn::make('guest.contact')
                ->label('CONTACT')
                ->searchable()
                ->sortable(),
            TextColumn::make('room.number')
                ->formatStateUsing(function ($record) {
                    return $record->room->numberWithFormat();
                })
                ->label('ROOM')
                ->weight('bold')
                ->searchable()
                ->sortable(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.manage.reservation', [
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

    public function updatedIsLongStay()
    {
        if ($this->is_longStay == true) {
            $this->rate_id = null;
        } else {
            $this->number_of_days = null;
        }
    }

    public function saveReservation()
    {

        if ($this->is_longStay == true) {
        // Long-stay reservation: charge the destination type's max 24h rate
        // multiplied by number_of_days. Without this multiplier the reservation
        // would lock in a 1-day rate while booking the room for N days.
        // (Mirrors Kiosk\CheckIn::proceedFillUp's long-stay calc.)
        $room_pay = Rate::where('branch_id', auth()->user()->branch_id)
            ->where('type_id', $this->type_id)
            ->max('amount') * (int) $this->number_of_days;
        $transaction = Guest::whereYear(
            'created_at',
            \Carbon\Carbon::today()->year
        )->count();
        $transaction += 1;
        $transaction_code =
            auth()->user()->branch_id .
            today()->format('y') .
            str_pad($transaction, 4, '0', STR_PAD_LEFT);
        $generatedQrCode = $transaction_code;
            $this->validate([
                'name' => 'required',
                'type_id' => 'required',
                'room_id' => 'required',
                'number_of_days' => 'required|numeric',
            ]);

            DB::beginTransaction();
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
            ]);
            TemporaryReserved::create([
                'branch_id' => auth()->user()->branch_id,
                'room_id' => $this->room_id,
                'guest_id' => $guest->id,
            ]);

            Room::where('id', $this->room_id)->update(['status' => 'Reserved']);

            Db::commit();
            $this->add_modal = false;
            $this->dialog()->success(
                $title = 'Reservation Added',
                $description = 'Reservation has been added successfully.'
            );
        } else {
        $room_pay = Rate::where('id', $this->rate_id)->first()?->amount;
        $transaction = Guest::whereYear(
            'created_at',
            \Carbon\Carbon::today()->year
        )->count();
        $transaction += 1;
        $transaction_code =
            auth()->user()->branch_id .
            today()->format('y') .
            str_pad($transaction, 4, '0', STR_PAD_LEFT);
        $generatedQrCode = $transaction_code;
            $this->validate([
                'name' => 'required',
                'type_id' => 'required',
                'room_id' => 'required',
                'rate_id' => 'required',
            ]);
            DB::beginTransaction();
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
            TemporaryReserved::create([
                'branch_id' => auth()->user()->branch_id,
                'room_id' => $this->room_id,
                'guest_id' => $guest->id,
            ]);
            Room::where('id', $this->room_id)->update(['status' => 'Reserved']);

            Db::commit();
            $this->add_modal = false;
            $this->dialog()->success(
                $title = 'Reservation Added',
                $description = 'Reservation has been added successfully.'
            );
        }
    }

    public function redirectToCheckInCO()
    {
        return redirect()->route('admin.check-in-co');
    }
}
