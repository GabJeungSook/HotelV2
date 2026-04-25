<?php

namespace App\Http\Livewire\Kiosk;

use Livewire\Component;
use App\Models\Type;
use App\Models\Room;
use App\Models\Rate;
use App\Models\Floor;
use WireUi\Traits\Actions;
use App\Models\Guest;
use App\Models\TemporaryCheckInKiosk;
use App\Models\CheckinDetail;
use App\Services\KioskBatchService;
use Carbon\Carbon;
use App\Jobs\TerminationInKiosk;
use App\Models\StayingHour;
use App\Events\CheckInEvent;
use App\Models\TemporaryReserved;
use DB;

class CheckIn extends Component
{
    use Actions;
    public $steps;
    public $types = [];
    // public $rooms = [];
    public $rates = [];
    public $floors = [];
    public $floor_id;
    public $type_id;
    public $room_id;
    public $rate_id;
    public $longstay;
    public $generatedQrCode;
    public $discount_available = false;
    public $discount_amount = 0;
    public $name, $contact;

    public $discountEnabled = false;
    //check_in details
    public $room_number, $room_type, $room_floor, $room_rate, $room_pay;

    public function render()
    {
        $branchId = auth()->user()->branch_id;

        // The batch is scoped per (branch, type). Without a selected type,
        // there is nothing to display — the kiosk's type-selection step runs
        // before this render shows rooms.
        $rooms = collect();

        if ($this->type_id) {
            // First render for this type (or after every active room was
            // picked): initialize / throw the next batch for this type.
            if (KioskBatchService::isEmpty($branchId, $this->type_id)) {
                KioskBatchService::throwNextBatch($branchId, $this->type_id);
            }

            // Self-heal: if active slots all point to rooms that became
            // unusable (Occupied/Maintenance/etc) via non-kiosk paths,
            // throw the next batch so the waiting stack is promoted.
            KioskBatchService::refreshIfStale($branchId, $this->type_id);

            $activeRoomIds = KioskBatchService::activeRoomIds($branchId, $this->type_id);

            $temporaryCheckInKiosk = TemporaryCheckInKiosk::where('branch_id', $branchId)
                ->pluck('room_id')
                ->toArray();

            $temporaryReserved = TemporaryReserved::where('branch_id', $branchId)
                ->pluck('room_id')
                ->toArray();

            // Exclude rooms with RECENT orphaned kiosk Guest records (hold
            // expired but guest never confirmed by frontdesk). Scoped to the
            // last 2 hours so stale historical orphans (e.g. guests with
            // transactions but no check-in detail that cleanup skips) do not
            // silently block all kiosk rooms. The kiosk:cleanup job runs every
            // minute, so a 2-hour window is far wider than any legitimate race.
            $pendingGuestRooms = Guest::where('branch_id', $branchId)
                ->whereDoesntHave('checkInDetail')
                ->where('created_at', '>=', now()->subHours(2))
                ->pluck('room_id')
                ->toArray();

            $rooms = Room::where('branch_id', $branchId)
                ->whereIn('id', $activeRoomIds)
                ->whereIn('status', ['Available', 'Cleaned'])
                ->whereNotIn('id', $temporaryCheckInKiosk)
                ->whereNotIn('id', $temporaryReserved)
                ->whereNotIn('id', $pendingGuestRooms)
                ->when($this->floor_id, function ($query) {
                    return $query->where('floor_id', $this->floor_id);
                })
                ->with(['type.rates', 'floor'])
                ->get()
                ->sortBy(function ($room) {
                    return $room->floor->number ?? 0;
                })
                ->values();
        }

        return view('livewire.kiosk.check-in', [
            'rooms' => $rooms,
        ]);
    }

    public function getTypes()
    {
        return $this->types = Type::whereBranchId(
            auth()->user()->branch_id
        )->get();
    }

    public function mount()
    {
        $this->getTypes();
        $this->floors = Floor::where('branch_id', auth()->user()->branch_id)->get();
        $this->discountEnabled = false;
        $this->discount_amount = 0;

        $this->steps = 1;
    }

    public function selectType($type_id)
    {
        $branchId = auth()->user()->branch_id;

        // Initialize the batch for this type if it has never been thrown.
        if (KioskBatchService::isEmpty($branchId, $type_id)) {
            KioskBatchService::throwNextBatch($branchId, $type_id);
        }

        // Self-heal stale batch (see KioskBatchService::refreshIfStale).
        KioskBatchService::refreshIfStale($branchId, $type_id);

        $activeRoomIds = KioskBatchService::activeRoomIds($branchId, $type_id);

        $temporaryCheckInKiosk = TemporaryCheckInKiosk::where('branch_id', $branchId)
            ->pluck('room_id')
            ->toArray();

        $temporaryReserved = TemporaryReserved::where('branch_id', $branchId)
            ->pluck('room_id')
            ->toArray();

        // Same 2-hour scope as render() — stale historical orphans must not
        // silently block the type-selection step either.
        $pendingGuestRooms = Guest::where('branch_id', $branchId)
            ->whereDoesntHave('checkInDetail')
            ->where('created_at', '>=', now()->subHours(2))
            ->pluck('room_id')
            ->toArray();

        $available = Room::where('branch_id', $branchId)
            ->where('type_id', $type_id)
            ->whereIn('id', $activeRoomIds)
            ->whereIn('status', ['Available', 'Cleaned'])
            ->whereNotIn('id', $temporaryCheckInKiosk)
            ->whereNotIn('id', $temporaryReserved)
            ->whereNotIn('id', $pendingGuestRooms)
            ->count();

        if ($available <= 0) {
            $this->dialog()->error(
                $title = 'SORRY',
                $description = 'There is no available room in this type.'
            );
        } else {
            $this->type_id = $type_id;
        }
    }

    public function updatedLongstay()
    {
        $this->rate_id = null;
    }

    public function selectRoom($room_id)
    {
        $this->room_id = $room_id;
        $this->rates = Rate::whereBranchId(auth()->user()->branch_id)
            ->whereTypeId($this->type_id)
            ->with(['stayingHour'])
            ->get();
    }

    public function selectRate($rate_id)
    {
        $this->rate_id = $rate_id;
        $this->longstay = null;
    }

    public function proceedFillUp()
    {
        $this->room_number = Room::where('id', $this->room_id)->first()->number;
        $this->room_floor = Room::where('id', $this->room_id)
            ->first()
            ->floor->numberWithFormat();
        $this->room_type = Type::where('id', $this->type_id)->first()->name;

        if ($this->longstay == null) {
            $this->room_rate = Rate::where(
                'id',
                $this->rate_id
            )->first()->stayingHour->number;

            $this->room_pay = Rate::where(
                'id',
                $this->rate_id
            )->first()->amount;
            $rate = Rate::where('id', $this->rate_id)->first();
            if ($rate->has_discount && auth()->user()->branch->discount_enabled) {
                $this->discount_available = true;
            }

            $this->steps = 4;
        } else {
            $this->validate([
                'longstay' => 'required|integer|min:1|max:31',
            ],[
                'longstay.required' => 'Please enter number of days.',
                'longstay.integer' => 'Number of days must be a whole number.',
                'longstay.min' => 'Number of days must be at least 1.',
                'longstay.max' => 'Number of days must not exceed 31.',
            ]);

            $long = StayingHour::where('branch_id', auth()->user()->branch_id)
                ->where('number', 24)
                ->first();

            $rate_exist = Rate::where('branch_id', auth()->user()->branch_id)
                ->where('type_id', $this->type_id)
                ->where('staying_hour_id', $long->id)
                ->exists();

            if ($rate_exist) {
                $this->room_rate =
                    Rate::where('branch_id', auth()->user()->branch_id)
                        ->where('type_id', $this->type_id)
                        ->max('amount') * $this->longstay;

                $this->rate_id = Rate::where(
                    'branch_id',
                    auth()->user()->branch_id
                )
                    ->where('type_id', $this->type_id)
                    ->where('staying_hour_id', $long->id)
                    ->first()->id;

                $this->room_pay = $this->room_rate;
                $this->steps = 4;
            } else {
                $this->dialog()->error(
                    $title = 'SORRY',
                    $description =
                        'Long Stay rate is not set yet. Please contact the administrator.'
                );
            }
        }
    }

    public function confirmTransaction()
    {
        if ($this->contact == null) {
            $this->validate([
                'name' => 'required|min:3',
            ]);
        } else {
            $this->validate([
                'name' => 'required|min:3',
                'contact' => 'required|min:9|max:9',
            ]);
        }

        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Confirm the Transaction?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, Confirm it',
                'method' => 'confirmCheckIn',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function confirmCheckIn()
    {

          DB::beginTransaction();

            try {

                $room = Room::where('branch_id', auth()->user()->branch_id)
                    ->where('id', $this->room_id)
                    ->where('status', 'Occupied')
                    ->with('latestCheckInDetail')
                    ->lockForUpdate()
                    ->first();

                if ($room) {
                    DB::rollBack();
                    $this->dialog()->error(
                        'SORRY',
                        'Room is already occupied. Please select another room.'
                    );
                    return;
                }

                $temporaryCheckInKiosk = TemporaryCheckInKiosk::where('branch_id', auth()->user()->branch_id)
                    ->where('room_id', $this->room_id)
                    ->lockForUpdate()
                    ->exists();

                $temporaryReserved = TemporaryReserved::where('branch_id', auth()->user()->branch_id)
                    ->where('room_id', $this->room_id)
                    ->lockForUpdate()
                    ->exists();

                if ($temporaryCheckInKiosk || $temporaryReserved) {
                    DB::rollBack();
                    $this->dialog()->error(
                        'SORRY',
                        'Room is already reserved. Please select another room.'
                    );
                    return;
                }

                /* TEMPORARILY DISABLED 2026-04-24 — unresolved-previous-guest guard.
                 * Blocked 4 existing stuck rooms; disabled to unblock operations
                 * while POS upgrade work is in progress. Re-enable after those stuck
                 * records are resolved AND POS work is shipped.
                 * See docs/bugs/2026-04-23-ghost-checkin-races-room-reuse.md
                 *
                 * $openCheckin = CheckinDetail::where('room_id', $this->room_id)
                 *     ->where('is_check_out', false)
                 *     ->with('guest:id,name')
                 *     ->lockForUpdate()
                 *     ->first();
                 *
                 * if ($openCheckin) {
                 *     DB::rollBack();
                 *     $ghostName = $openCheckin->guest->name ?? 'Unknown';
                 *     $ghostDate = $openCheckin->check_in_at
                 *         ? Carbon::parse($openCheckin->check_in_at)->format('M d, Y g:i A')
                 *         : 'unknown date';
                 *     $this->dialog()->error(
                 *         'SORRY',
                 *         "Room has unresolved previous guest: {$ghostName} (checked in {$ghostDate}). Please contact the front desk."
                 *     );
                 *     return;
                 * }
                 */

              $transaction = Guest::whereYear('created_at', now()->year)
                    ->lockForUpdate()
                    ->count();

                $transaction += 1;

                // limit to 4 digits
                $transaction = $transaction % 10000;

                $transaction_code =
                    auth()->user()->branch_id .
                    now()->format('y') .
                    str_pad($transaction, 4, '0', STR_PAD_LEFT);

                // $transaction_code = sprintf('%05d', random_int(0, 99999));

                $this->generatedQrCode = $transaction_code;

                $guest = Guest::create([
                    'branch_id' => auth()->user()->branch_id,
                    'name' => $this->name,
                    'contact' => $this->contact ? '09' . $this->contact : 'N/A',
                    'qr_code' => $transaction_code,
                    'room_id' => $this->room_id,
                    'rate_id' => $this->rate_id,
                    'type_id' => $this->type_id,
                    'static_amount' => $this->room_pay,
                    'is_long_stay' => $this->longstay ? true : false,
                    'number_of_days' => $this->longstay ?? 0,
                    'has_discount' => $this->discountEnabled,
                    'discount_amount' => $this->discountEnabled ? $this->discount_amount : 0,
                ]);

                TemporaryCheckInKiosk::create([
                    'guest_id' => $guest->id,
                    'room_id' => $this->room_id,
                    'branch_id' => auth()->user()->branch_id,
                    'terminated_at' => now()->addMinutes(20),
                ]);

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e; // do not silently swallow errors in production
            }

            // Mark this room's batch slot as picked. If this drains the last
            // active slot, the service auto-throws the next batch.
            KioskBatchService::markPicked(auth()->user()->branch_id, $this->room_id);

            $this->steps = 5;
    }

    public function redirectToHome()
    {
        return redirect()->route('kiosk.house-rules');
    }

    public function applyDiscount()
    {
      
       if($this->discountEnabled) {
        $this->discount_amount = auth()->user()->branch->discount_amount;
       } else {
        $this->discount_amount = 0;
       }
    }

    public function backRoom()
    {
        $this->floor_id = null;
    }
}
