<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('branches', 'pos_v2_enabled')) {
            return;
        }
        Schema::table('branches', function (Blueprint $table) {
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
