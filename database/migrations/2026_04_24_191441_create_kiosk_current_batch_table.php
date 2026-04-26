<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('kiosk_current_batch')) {
            return;
        }
        Schema::create('kiosk_current_batch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->foreignId('type_id');
            $table->foreignId('room_id');
            $table->foreignId('floor_id');
            $table->enum('slot_status', ['active', 'picked'])->default('active');
            $table->timestamps();

            // Each type rotates its own batch independently per branch.
            $table->index(['branch_id', 'type_id', 'slot_status']);
            $table->index(['branch_id', 'type_id', 'floor_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kiosk_current_batch');
    }
};
