<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->index('branch_id');
            $table->index('floor_id');
            $table->index('type_id');
            $table->index('status');
            $table->index(['branch_id', 'status']);
        });

        Schema::table('checkin_details', function (Blueprint $table) {
            $table->index('room_id');
            $table->index('guest_id');
            $table->index('is_check_out');
            $table->index(['room_id', 'is_check_out', 'created_at']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->index('room_id');
            $table->index('branch_id');
            $table->index('has_kiosk_check_out');
        });

        Schema::table('new_guest_reports', function (Blueprint $table) {
            $table->index('room_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('room_id');
            $table->index('guest_id');
            $table->index('branch_id');
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->index('type_id');
            $table->index('branch_id');
        });
    }

    public function down()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['branch_id']);
            $table->dropIndex(['floor_id']);
            $table->dropIndex(['type_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['branch_id', 'status']);
        });

        Schema::table('checkin_details', function (Blueprint $table) {
            $table->dropIndex(['room_id']);
            $table->dropIndex(['guest_id']);
            $table->dropIndex(['is_check_out']);
            $table->dropIndex(['room_id', 'is_check_out', 'created_at']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['room_id']);
            $table->dropIndex(['branch_id']);
            $table->dropIndex(['has_kiosk_check_out']);
        });

        Schema::table('new_guest_reports', function (Blueprint $table) {
            $table->dropIndex(['room_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['room_id']);
            $table->dropIndex(['guest_id']);
            $table->dropIndex(['branch_id']);
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->dropIndex(['type_id']);
            $table->dropIndex(['branch_id']);
        });
    }
};
