<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use DB;
use Carbon\Carbon;
use App\Models\Menu;
use App\Models\Rate;
use App\Models\Room;
use App\Models\Type;
use App\Models\Floor;
use App\Models\Guest;
use Livewire\Component;
use App\Models\Inventory;
use App\Models\User;
use WireUi\Traits\Actions;
use App\Models\StayingHour;
use App\Models\Transaction;
use App\Models\CheckinDetail;
use App\Models\NewGuestReport;
use App\Models\AssignedFrontdesk;
use App\Models\TemporaryReserved;
use App\Models\TemporaryCheckInKiosk;
use App\Models\KioskCurrentBatch;
use App\Services\KioskBatchService;

class RoomMonitoring extends Component
{
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
        $additional_charges,
        $has_discount = false,
        $discount_amount;
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
    public $food_beverages_modal = false;

    public $food_id;
    public $food_price;
    public $food_quantity;
    public $food_total_amount;
    public $assigned_frontdesk;

    public $type_id;
    public $room_id;
    public $rate_id;
    public $is_longStay;
    public $number_of_days;
    public $name;
    public $contact_number;

    public $listener_identifier;
    public $checkInDetails = [];

    public $temporary_checkInKiosk;

    // Kiosk batch viewer (modal triggered from this page).
    public $kioskBatchModal = false;
    public $kioskBatchData = [];
    public $kioskBatchTotals = [];

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
        $this->assigned_frontdesk = auth()->user()->assigned_frontdesks;
        $this->food_price = 0;
    }

    public function redirectToScanning()
    {
      return redirect()->route('frontdesk.scan-qr-code');
    }

    /**
     * Open the "View Kiosk Batch" modal so frontdesk can see what's
     * displayed on the kiosk right now, plus what's coming next, without
     * walking over to the kiosk station.
     */
    public function showKioskBatch()
    {
        $branchId = auth()->user()->branch_id;
        $types = Type::where('branch_id', $branchId)->orderBy('id')->get();

        $data = [];
        foreach ($types as $type) {
            $current = KioskCurrentBatch::where('branch_id', $branchId)
                ->where('type_id', $type->id)
                ->with(['room:id,number,status', 'floor:id,number'])
                ->get()
                ->sortBy(fn ($b) => $b->floor->number ?? 0)
                ->values()
                ->map(function ($b) {
                    return [
                        'floor_number' => $b->floor->number ?? '?',
                        'room_number' => $b->room->number ?? '?',
                        'slot_status' => $b->slot_status,
                        'room_status' => $b->room->status ?? '?',
                    ];
                })
                ->toArray();

            // Show 2 upcoming batches (Batch +1 and Batch +2) on top of current.
            $upcoming = KioskBatchService::previewBatches($branchId, $type->id, 2);

            // Total available rooms of this type across the whole branch
            // (not just inside the current batch). This is the figure
            // frontdesk usually wants to know — "how many Singles do we
            // actually have ready right now?".
            $totalAvailable = Room::where('branch_id', $branchId)
                ->where('type_id', $type->id)
                ->whereIn('status', ['Available', 'Cleaned'])
                ->where('is_priority', 1)
                ->count();

            $batchRoomIds = collect($current)->pluck('floor_number'); // not used, kept for clarity
            $waitingCount = max(0, $totalAvailable - collect($current)
                ->where('slot_status', 'active')
                ->count());

            $data[] = [
                'type_id' => $type->id,
                'type_name' => $type->name,
                'current' => $current,
                'upcoming' => $upcoming,
                'total_available' => $totalAvailable,
                'waiting_count' => $waitingCount,
            ];
        }

        // Branch-wide grand totals.
        $grandAvailable = Room::where('branch_id', $branchId)
            ->whereIn('status', ['Available', 'Cleaned'])
            ->where('is_priority', 1)
            ->count();
        $grandOccupied = Room::where('branch_id', $branchId)
            ->where('status', 'Occupied')
            ->count();
        $grandTotal = Room::where('branch_id', $branchId)->count();

        $this->kioskBatchTotals = [
            'available' => $grandAvailable,
            'occupied' => $grandOccupied,
            'total' => $grandTotal,
        ];

        $this->kioskBatchData = $data;
        $this->kioskBatchModal = true;
    }

    public function closeKioskBatchModal()
    {
        $this->kioskBatchModal = false;
        $this->kioskBatchData = [];
    }

    public function redirectToCheckinFromKiosk($id)
    {
          $temp = TemporaryCheckInKiosk::where('id', $id)
            ->first();
        //   $temp->update([
        //     'is_opened' => true,
        //   ]);
          return redirect()->route('frontdesk.check-in-from-kiosk', $id);
    }


    public function addTransaction($id)
    {
        $this->guest = Guest::find($id);
        $this->food_beverages_modal = true;
    }

    public function updatedFoodId()
    {
        if ($this->food_id != 'Select Item') {
            $price = Menu::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->food_id)
                ->first()->price;
            if ($this->food_quantity == null || $this->food_quantity == 0) {
                $this->food_price = $price * 1;
                $this->food_total_amount = $price * 1;
            } else {
                $this->food_price = $price * $this->food_quantity;
                $this->food_total_amount = $price * $this->food_quantity;
            }
        } else {
            $this->food_price = 0;
        }
    }

    public function updatedFoodQuantity()
    {
        if ($this->food_id != 'Select Item') {
            $price = Menu::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->food_id)
                ->first()->price;
            if ($this->food_quantity == null || $this->food_quantity == 0) {
                $this->food_price = $price;
                $this->food_total_amount = $price * 1;
            } else {
                $this->food_price = $price;
                $this->food_total_amount = $price * $this->food_quantity;
            }
        } else {
            $this->food_price = 0;
        }
    }

    public function closeModal()
    {
        return redirect()->route('kitchen.transactions');
    }

    public function addFood()
    {
        $this->validate(
            [
                'food_id' => 'required',
                'food_quantity' => 'required|gt:0',
            ],
            [
                'food_id.required' => 'This field is required',
                'food_quantity.required' => 'This field is required',
            ]
        );
        DB::beginTransaction();
        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest->id
        )->first();

        $food = Menu::where('branch_id', auth()->user()->branch_id)
            ->where('id', $this->food_id)
            ->first();
        $inventory = Inventory::where('branch_id', auth()->user()->branch_id)
            ->where('menu_id', $this->food_id)
            ->first();
        if($inventory != null)
        {
            $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
            Transaction::create([
                'branch_id' => $check_in_detail->guest->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $check_in_detail->id,
                'cash_drawer_id' => $check_in_detail->guest->cash_drawer_id,
                'room_id' => $check_in_detail->room_id,
                'guest_id' => $check_in_detail->guest_id,
                'floor_id' => $check_in_detail->room->floor_id,
                'transaction_type_id' => 9,
                'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
                'description' => 'Food and Beverages',
                'payable_amount' => $this->food_total_amount,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => null,
                'override_at' => null,
                'remarks' =>
                'Guest Added Food and Beverages: (Kitchen) (' .$this->food_quantity .')' .' '.$food->name,
                'shift' => (now()->hour >= 8 && now()->hour < 20)
                    ? 'AM'
                    : 'PM',
            ]);
            //update stock
            $new_stock =
                $inventory->number_of_serving - $this->food_quantity;
            $inventory->update([
                'number_of_serving' => $new_stock,
            ]);

            $this->food_beverages_modal = false;
            $this->dialog()->success(
                $title = 'Success',
                $description = 'Transaction Added Successfully',
            );
        }else{
            $this->dialog()->error(
                $title = 'Out Of Stock',
                $description = 'This item is out of stock',
            );
        }
        DB::commit();

        // return redirect()->route('kitchen.transactions');
    }

    public function fixGhostRoom($roomId)
    {
        $room = Room::find($roomId);

        if (!$room) {
            $this->dialog()->error('Error', 'Room not found.');
            return;
        }

        $hasActiveGuest = CheckinDetail::where('room_id', $room->id)
            ->where('is_check_out', false)
            ->exists();

        if ($hasActiveGuest) {
            $this->dialog()->error('Error', 'This room has an active guest. Cannot fix.');
            return;
        }

        $room->update(['status' => 'Available']);

        \App\Models\ActivityLog::create([
            'branch_id' => $room->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Fix Ghost Room',
            'description' => 'Fixed ghost room #' . $room->number . ' - changed from Occupied to Available (frontdesk)',
        ]);

        $this->dialog()->success('Fixed', 'Room #' . $room->number . ' is now Available.');
    }

    public function getGracePeriodCountProperty()
    {
        $now = now();
        $graceCutoff = now()->subMinutes(15);

        return Room::where('branch_id', auth()->user()->branch_id)
            ->where('status', 'Occupied')
            ->whereHas('latestCheckInDetail', function ($q) use ($now, $graceCutoff) {
                $q->where('is_check_out', false)
                  ->where('check_out_at', '<', $now)
                  ->where('check_out_at', '>=', $graceCutoff);
            })
            ->count();
    }

    // Kept commented for quick re-enable of the OVER TIME sidebar section.
    // public function getOverTimeCountProperty()
    // {
    //     $graceCutoff = now()->subMinutes(15);
    //
    //     return Room::where('branch_id', auth()->user()->branch_id)
    //         ->where('status', 'Occupied')
    //         ->whereHas('latestCheckInDetail', function ($q) use ($graceCutoff) {
    //             $q->where('is_check_out', false)
    //               ->where('check_out_at', '<', $graceCutoff);
    //         })
    //         ->count();
    // }
    //
    // public function getOverTimeRoomsProperty()
    // {
    //     $graceCutoff = now()->subMinutes(15);
    //
    //     return Room::where('branch_id', auth()->user()->branch_id)
    //         ->where('status', 'Occupied')
    //         ->whereHas('latestCheckInDetail', function ($q) use ($graceCutoff) {
    //             $q->where('is_check_out', false)
    //               ->where('check_out_at', '<', $graceCutoff);
    //         })
    //         ->with(['latestCheckInDetail.guest', 'floor', 'type'])
    //         ->get();
    // }

    public function render()
    {
        // Get room IDs that have ghost records (Available but has open check-in)
        $ghostRoomIds = CheckinDetail::where('is_check_out', false)
            ->pluck('room_id')
            ->toArray();

        // Count how many of these ghost records affect rooms that are currently "Available" or similar
        // (rooms that should be bookable but have unresolved check-ins)
        $ghostCount = count($ghostRoomIds);

        return view('livewire.frontdesk.monitoring.room-monitoring', [
             'rooms' => $this->searchRooms(),
            'kiosks' => $this->searchKiosk(),
            'checkOutKiosks' => $this->searchCheckOutKiosk(),
            'foods' => $this->food_beverages_modal
                ? Menu::where('branch_id', auth()->user()->branch_id)->get()
                : collect(),
            'kioskBatchData' => $this->kioskBatchData,
            'kioskBatchTotals' => $this->kioskBatchTotals,
            'gracePeriodCount' => $this->gracePeriodCount,
            'ghostRoomIds' => $ghostRoomIds, // For ghost record warning indicator
            'ghostCount' => $ghostCount, // Count for header display
            // 'overTimeCount' => $this->overTimeCount,
            // 'overTimeRooms' => $this->overTimeRooms,
        ]);
    }

    public function fifo()
    {
        $this->dialog()->error(
            $title = 'Oops!',
            $description = 'This is on queue.'
        );
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

        return TemporaryCheckInKiosk::with(['guest', 'guest.room'])
            ->where('branch_id', auth()->user()->branch_id)
            ->where('is_opened', false)
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
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function searchCheckOutKiosk()
    {
        return Room::with(['latestCheckInDetail.guest'])
            ->where('branch_id', auth()->user()->branch_id)
            ->where('status', 'Occupied')
            ->whereHas('latestCheckInDetail', function ($query) {
                $query->where('is_check_out', false);
            })
            ->whereHas('latestCheckInDetail.guest', function ($query) {
                $query->where('has_kiosk_check_out', true);
            })
            ->orderBy('updated_at', 'desc')
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

    public function deleteTempKiosk($id)
    {
        $temp = TemporaryCheckInKiosk::where('id', $id)->first();

        if (! $temp) {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'Temporary Check In Not Found'
            );
            return;
        }

        $branchId = $temp->branch_id;
        $roomId = $temp->room_id;
        $guestId = $temp->guest_id;

        DB::transaction(function () use ($temp, $guestId) {
            // Mirror the safety rule used by cancelCheckIn / kiosk:cleanup —
            // never delete a Guest that has real transactions or an existing
            // checkin_detail (would orphan accounting records).
            if ($guestId) {
                Guest::where('id', $guestId)
                    ->whereDoesntHave('checkInDetail')
                    ->whereDoesntHave('transactions')
                    ->delete();
            }
            $temp->delete();
        });

        // Return the batch slot to 'active' so the room reappears on the kiosk.
        // Without this the slot stays 'picked' (cross-mark in batch viewer)
        // until the next batch throw, which is wrong UX after a manual cancel.
        KioskBatchService::returnToBatch($branchId, $roomId);

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Temporary Check In Deleted Successfully'
        );
        return redirect()->route('frontdesk.room-monitoring');
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
        return Room::where('rooms.branch_id', auth()->user()->branch_id)
        ->where('rooms.status', 'Occupied')

        ->when($this->filter_status, function ($query) {
            return $query->where('rooms.status', $this->filter_status);
        })

        ->when($this->filter_floor, function ($query) {
            return $query->where('rooms.floor_id', $this->filter_floor);
        })

        ->when($this->search, function ($query) {
            return $query->where('rooms.number', 'like', $this->search);
        })

        ->with([
            'floor',
            'type',
            'latestCheckInDetail.guest',
            'newGuestReports',
        ])

        ->leftJoin('checkin_details', function ($join) {
            $join->on('rooms.id', '=', 'checkin_details.room_id')
                 ->where('checkin_details.is_check_out', false)
                 ->whereRaw('checkin_details.id = (
                     SELECT MAX(cd.id) FROM checkin_details cd
                     WHERE cd.room_id = rooms.id AND cd.is_check_out = 0
                 )');
        })

        ->leftJoin('guests', function ($join) {
            $join->on('checkin_details.guest_id', '=', 'guests.id');
        })

        ->selectRaw('
            rooms.*,
            COALESCE(checkin_details.check_out_at, NULL) AS check_out_at,
            (CASE WHEN rooms.status = "Occupied" THEN 1 ELSE 0 END) AS is_occupied,
            (CASE WHEN guests.has_kiosk_check_out = 1 THEN 1 ELSE 0 END) AS has_kiosk_priority
        ')

        ->orderByRaw('
            has_kiosk_priority DESC,
            is_occupied DESC,
            check_out_at ASC
        ')

        ->distinct()
        ->get();



        // return Room::where('branch_id', auth()->user()->branch_id)
        //     ->where('status', 'Occupied')
        //     ->when($this->filter_status, function ($query) {
        //         return $query->where('status', $this->filter_status);
        //     })
        //     ->when($this->filter_floor, function ($query) {
        //         return $query->where('floor_id', $this->filter_floor);
        //     })
        //     ->when($this->search, function ($query) {
        //         return $query->where('number', 'like',  $this->search . '%');
        //     })
        //     ->with('floor')
        //     ->with(['checkInDetails' => function ($query) {
        //         $query->where('is_check_out', false)  // Filter where is_check_out is false
        //           ->orderBy('check_out_at', 'asc');
        //     }])
        //     ->selectRaw('rooms.*, COALESCE(checkin_details.check_out_at, NULL) AS check_out_at,
        //                 (CASE WHEN status = "Occupied" THEN 1 ELSE 0 END) AS is_occupied') // Add calculated column for occupancy status
        //     ->whereRaw('(checkin_details.is_check_out IS NULL OR checkin_details.is_check_out = 0)')
        //     ->leftJoin('checkin_details', function ($join) {
        //         $join->on('rooms.id', '=', 'checkin_details.room_id');
        //     }) // Join checkInDetails
        //     ->orderByRaw('is_occupied DESC, check_out_at ASC') // Order by occupancy status first, then by check_out_at
        //     ->distinct() // Ensure distinct rooms
        //     ->paginate(10);
    }

    // public function searchRooms()
    // {
    //     $branchId = auth()->user()->branch_id;

    //     $latestCheckOutSubquery = \DB::table('checkin_details as cid')
    //         ->select('cid.room_id', \DB::raw('MAX(cid.check_out_at) as latest_check_out_at'))
    //         ->groupBy('cid.room_id');

    //     return Room::where('branch_id', $branchId)
    //         ->when($this->filter_status, function ($query) {
    //             return $query->where('status', $this->filter_status);
    //         })
    //         ->when($this->filter_floor, function ($query) {
    //             return $query->where('floor_id', $this->filter_floor);
    //         })
    //         ->when($this->search, function ($query) {
    //             return $query->where('number', 'like', '%' . $this->search . '%');
    //         })
    //         ->leftJoinSub($latestCheckOutSubquery, 'latest_check_out', function ($join) {
    //             $join->on('rooms.id', '=', 'latest_check_out.room_id');
    //         })
    //         ->with('floor')
    //         ->with(['checkInDetails' => function ($query) {
    //             $query->orderBy('check_out_at', 'asc');
    //         }])
    //         ->select('rooms.*', 'latest_check_out.latest_check_out_at as check_out_at')
    //         ->orderByRaw('(CASE WHEN latest_check_out.latest_check_out_at IS NULL THEN 1 ELSE 0 END), latest_check_out.latest_check_out_at ASC')
    //         ->paginate(10);
    // }

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

    public function updatedHasDiscount()
    {
        //compute total amount
         if ($this->has_discount) {
                $this->total = ($this->guest->static_amount + $this->additional_charges) - $this->discount_amount;
            } else {
                $this->total = $this->guest->static_amount + $this->additional_charges;
        }
        //check if amount paid is greater than total
        if ($this->amountPaid > $this->total) {
            $this->excess = true;
            $this->excess_amount = $this->amountPaid - $this->total;
        } else {
            $this->excess = false;
            $this->excess_amount = 0;
        }
    }

    public function checkIn($id)
    {
            $this->additional_charges = auth()->user()->branch->initial_deposit;
            $this->excess_amount = 0;
            $this->temporary_checkIn = TemporaryCheckInKiosk::where(
                'branch_id',
                auth()->user()->branch_id
            )
                ->where('id', $id)
                ->first();

            if (! $this->temporary_checkIn) {
                $this->dialog()->error('Not found', 'This kiosk record no longer exists. It may have already been processed or expired.');
                return;
            }

            $this->guest = Guest::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->temporary_checkIn->guest_id)
                ->first();

            if (! $this->guest) {
                $this->dialog()->error('Guest record missing', 'The guest associated with this kiosk record could not be found. Please ask the guest to use the kiosk again or contact support.');
                $this->temporary_checkIn = null;
                return;
            }
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
            $this->has_discount = $this->guest->has_discount;
            $this->discount_amount = auth()->user()->branch->discount_amount;

            if ($this->has_discount) {
                $this->total = ($this->guest->static_amount + $this->additional_charges) - $this->discount_amount;
            } else {
                $this->total = $this->guest->static_amount + $this->additional_charges;
            }
            return $this->checkInModal = true;

    }

    public function checkInReserve($id)
    {
        $this->additional_charges_reserve = auth()->user()->branch->initial_deposit;
        $this->excess_amount_reserve = 0;
        $this->temporary_reserve = TemporaryReserved::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('room_id', $id)
            ->first();

        if (! $this->temporary_reserve) {
            $this->dialog()->error('Not found', 'This reservation record no longer exists.');
            return;
        }

        $this->guest_reserve = Guest::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $this->temporary_reserve->guest_id)
            ->first();

        if (! $this->guest_reserve) {
            $this->dialog()->error('Guest record missing', 'The guest associated with this reservation could not be found.');
            $this->temporary_reserve = null;
            return;
        }
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
        $decode_frontdesk = json_decode(
            auth()->user()->assigned_frontdesks,
            true
        );

        // Block check-in if room has unresolved previous guest
        // Changed from auto-close to block (2026-04-28) to ensure proper checkout flow
        $existingCheckin = CheckinDetail::where('room_id', $this->room_id)
            ->where('is_check_out', false)
            ->with('guest:id,name')
            ->first();

        if ($existingCheckin) {
            $ghostName = $existingCheckin->guest->name ?? 'Unknown';
            $ghostDate = $existingCheckin->check_in_at
                ? \Carbon\Carbon::parse($existingCheckin->check_in_at)->format('M d, Y g:i A')
                : 'unknown date';
            $this->dialog()->error(
                'Room Has Active Guest',
                "Room has unresolved check-in: {$ghostName} (checked in {$ghostDate}). Please checkout the previous guest first via Guest Transaction."
            );
            return;
        }

        $checkin = CheckinDetail::create([
            'guest_id' => $guest->id,
            'frontdesk_id' => $decode_frontdesk[0],
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
        $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        if ($this->save_excess) {
            $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
            Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $checkin->id,
                'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
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

    private function isUserOnline($user, $threshold) { return $user->sessions() ->where('last_activity', '>=', $threshold) ->exists(); }

    public function saveCheckInDetails()
    {
        $this->validate([
            'amountPaid' => 'required|gte:' . $this->total,
        ]);

        DB::beginTransaction();
         $decode_frontdesk = json_decode(
            auth()->user()->assigned_frontdesks,
            true
        );
        $number_of_hours = $this->stayingHour->number;
        $next_extension_is_original = false;
        while ($number_of_hours >= auth()->user()->branch->extension_time_reset) {
            $number_of_hours -= auth()->user()->branch->extension_time_reset;
            $next_extension_is_original = true;
        }

        // Block check-in if room has unresolved previous guest
        // Changed from auto-close to block (2026-04-28) to ensure proper checkout flow
        $existingCheckinKiosk = CheckinDetail::where('room_id', $this->guest->room_id)
            ->where('is_check_out', false)
            ->with('guest:id,name')
            ->first();

        if ($existingCheckinKiosk) {
            $ghostName = $existingCheckinKiosk->guest->name ?? 'Unknown';
            $ghostDate = $existingCheckinKiosk->check_in_at
                ? \Carbon\Carbon::parse($existingCheckinKiosk->check_in_at)->format('M d, Y g:i A')
                : 'unknown date';
            $this->dialog()->error(
                'Room Has Active Guest',
                "Room has unresolved check-in: {$ghostName} (checked in {$ghostDate}). Please checkout the previous guest first via Guest Transaction."
            );
            return;
        }

        $checkin = CheckinDetail::create([
            'guest_id' => $this->guest->id,
            'frontdesk_id' => $decode_frontdesk[0],
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
            'number_of_hours' => $number_of_hours,
            'next_extension_is_original' => $next_extension_is_original ? 1 : 0,
        ]);
        $room_number = Room::where('id', $this->guest->room_id)->first()
            ->number;
        $assigned_frontdesk = auth()->user()->assigned_frontdesks;
        $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;

        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        if ($this->save_excess) {
            $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
            Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $checkin->id,
                'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);
        }

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
        return redirect()->route('frontdesk.room-monitoring');
    }

    public function saveReserveCheckInDetails()
    {
        $this->validate([
            'amountPaid_reserve' => 'required|gte:' . $this->total_reserve,
        ]);

        DB::beginTransaction();
         $decode_frontdesk = json_decode(
            auth()->user()->assigned_frontdesks,
            true
        );

        $number_of_hours_reserve = $this->stayingHour_reserve->number;
        $next_extension_is_original_reserve = false;
        while ($number_of_hours_reserve >= auth()->user()->branch->extension_time_reset) {
            $number_of_hours_reserve -= auth()->user()->branch->extension_time_reset;
            $next_extension_is_original_reserve = true;
        }

        // Block check-in if room has unresolved previous guest
        // Changed from auto-close to block (2026-04-28) to ensure proper checkout flow
        $existingCheckinReserve = CheckinDetail::where('room_id', $this->guest_reserve->room_id)
            ->where('is_check_out', false)
            ->with('guest:id,name')
            ->first();

        if ($existingCheckinReserve) {
            $ghostName = $existingCheckinReserve->guest->name ?? 'Unknown';
            $ghostDate = $existingCheckinReserve->check_in_at
                ? \Carbon\Carbon::parse($existingCheckinReserve->check_in_at)->format('M d, Y g:i A')
                : 'unknown date';
            $this->dialog()->error(
                'Room Has Active Guest',
                "Room has unresolved check-in: {$ghostName} (checked in {$ghostDate}). Please checkout the previous guest first via Guest Transaction."
            );
            return;
        }

        $checkin = CheckinDetail::create([
            'guest_id' => $this->guest_reserve->id,
            'frontdesk_id' => $decode_frontdesk[0],
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
            'number_of_hours' => $number_of_hours_reserve,
            'next_extension_is_original' => $next_extension_is_original_reserve ? 1 : 0,
        ]);
        $room_number = Room::where('id', $this->guest_reserve->room_id)->first()

            ->number;
        $assigned_frontdesk = auth()->user()->assigned_frontdesks;
        $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
            'is_co' => true,
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;

        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
            'is_co' => true,
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        if ($this->save_excess_reserve) {
            $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
            Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $checkin->id,
                'cash_drawer_id' => $checkin->guest->cash_drawer_id,
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
                'is_co' => true,
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
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
        return redirect()->route('frontdesk.room-monitoring');
    }

    public function redirectToCheckInCO()
    {
        return redirect()->route('frontdesk.check-in-co-frontdesk');
    }
}
