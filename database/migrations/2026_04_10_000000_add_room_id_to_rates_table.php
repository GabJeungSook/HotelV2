<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Step 1: Add room_id as nullable (safe — doesn't break existing queries)
        Schema::table('rates', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->after('type_id');
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
        });

        // Step 2: Backfill — for each existing rate (type_id + staying_hour),
        // create a copy for every room of that type.
        $existingRates = DB::table('rates')->whereNull('room_id')->get();

        foreach ($existingRates as $rate) {
            $rooms = DB::table('rooms')
                ->where('type_id', $rate->type_id)
                ->where('branch_id', $rate->branch_id)
                ->get();

            if ($rooms->isEmpty()) {
                continue;
            }

            // Assign the first room to the existing rate row
            $firstRoom = $rooms->shift();
            DB::table('rates')->where('id', $rate->id)->update(['room_id' => $firstRoom->id]);

            // Create new rate rows for the remaining rooms
            foreach ($rooms as $room) {
                DB::table('rates')->insert([
                    'branch_id' => $rate->branch_id,
                    'staying_hour_id' => $rate->staying_hour_id,
                    'type_id' => $rate->type_id,
                    'room_id' => $room->id,
                    'amount' => $rate->amount,
                    'is_available' => $rate->is_available,
                    'has_discount' => $rate->has_discount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        // Remove duplicated rates (keep only one per type_id + staying_hour_id + branch_id)
        $groups = DB::table('rates')
            ->select('type_id', 'staying_hour_id', 'branch_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('type_id', 'staying_hour_id', 'branch_id')
            ->get();

        $keepIds = $groups->pluck('keep_id')->toArray();
        if (!empty($keepIds)) {
            DB::table('rates')->whereNotIn('id', $keepIds)->delete();
        }

        Schema::table('rates', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropColumn('room_id');
        });
    }
};
