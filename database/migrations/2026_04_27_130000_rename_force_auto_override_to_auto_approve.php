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
        Schema::table('branches', function (Blueprint $table) {
            $table->renameColumn('force_auto_override', 'auto_approve_enabled');
            $table->renameColumn('force_auto_override_by', 'auto_approve_enabled_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->renameColumn('auto_approve_enabled', 'force_auto_override');
            $table->renameColumn('auto_approve_enabled_by', 'force_auto_override_by');
        });
    }
};
