<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('shift_logs', function (Blueprint $table) {
            $table->decimal('total_pos', 15, 2)->default(0)->after('total_remittances');
        });
    }

    public function down()
    {
        Schema::table('shift_logs', function (Blueprint $table) {
            $table->dropColumn('total_pos');
        });
    }
};
