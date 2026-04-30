<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dateTime('last_cleaned_at')->nullable()->after('last_checkout_at');
        });

        // Backfill from cleaning_histories (latest end_time per room) so the
        // FIFO-by-cleaning-time priority works on day one without waiting for
        // a fresh cleaning cycle on every room.
        DB::statement('
            UPDATE rooms r
            INNER JOIN (
                SELECT room_id, MAX(end_time) AS latest_end
                FROM cleaning_histories
                WHERE end_time IS NOT NULL
                GROUP BY room_id
            ) ch ON ch.room_id = r.id
            SET r.last_cleaned_at = ch.latest_end
        ');
    }

    public function down()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('last_cleaned_at');
        });
    }
};
