<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('frontdesk_inventories', function (Blueprint $table) {
            $table->double('warehouse_stock')->default(0)->after('number_of_serving');
        });
    }

    public function down()
    {
        Schema::table('frontdesk_inventories', function (Blueprint $table) {
            $table->dropColumn('warehouse_stock');
        });
    }
};
