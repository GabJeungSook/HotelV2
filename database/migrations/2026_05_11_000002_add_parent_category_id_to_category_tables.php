<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->foreignId('parent_category_id')->nullable()->constrained('parent_categories')->nullOnDelete();
        });

        Schema::table('frontdesk_categories', function (Blueprint $table) {
            $table->foreignId('parent_category_id')->nullable()->constrained('parent_categories')->nullOnDelete();
        });

        Schema::table('pub_categories', function (Blueprint $table) {
            $table->foreignId('parent_category_id')->nullable()->constrained('parent_categories')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_category_id');
        });

        Schema::table('frontdesk_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_category_id');
        });

        Schema::table('pub_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_category_id');
        });
    }
};
