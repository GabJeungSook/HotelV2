<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change guest_id and checkin_detail_id from cascade to set null
     * so that override request records are preserved for stats/history
     */
    public function up()
    {
        Schema::table('override_requests', function (Blueprint $table) {
            // Drop existing foreign keys
            $table->dropForeign(['guest_id']);
            $table->dropForeign(['checkin_detail_id']);

            // Re-add with set null instead of cascade
            $table->foreign('guest_id')
                ->references('id')
                ->on('guests')
                ->onDelete('set null');

            $table->foreign('checkin_detail_id')
                ->references('id')
                ->on('checkin_details')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('override_requests', function (Blueprint $table) {
            // Drop the modified foreign keys
            $table->dropForeign(['guest_id']);
            $table->dropForeign(['checkin_detail_id']);

            // Re-add with original cascade behavior
            $table->foreign('guest_id')
                ->references('id')
                ->on('guests')
                ->onDelete('cascade');

            $table->foreign('checkin_detail_id')
                ->references('id')
                ->on('checkin_details')
                ->onDelete('cascade');
        });
    }
};
