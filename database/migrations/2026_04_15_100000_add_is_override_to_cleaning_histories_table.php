<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('cleaning_histories', function (Blueprint $table) {
            $table->boolean('is_override')->default(false)->after('delayed_cleaning');
        });
    }

    public function down()
    {
        Schema::table('cleaning_histories', function (Blueprint $table) {
            $table->dropColumn('is_override');
        });
    }
};
