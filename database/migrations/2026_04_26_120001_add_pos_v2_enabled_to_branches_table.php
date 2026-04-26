<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Pilot rollout flag for the rebuilt POS register UI (Plan 2).
            // Default false so all existing branches keep v1 until explicitly opted in.
            $table->boolean('pos_v2_enabled')->default(false)->after('force_auto_override');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('pos_v2_enabled');
        });
    }
};
