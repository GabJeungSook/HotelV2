<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('menu_ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('menu_type');
            $table->unsignedBigInteger('menu_id');
            $table->string('ingredient_type');
            $table->unsignedBigInteger('ingredient_menu_id');
            $table->decimal('quantity', 8, 2);
            $table->timestamps();

            $table->unique(
                ['menu_type', 'menu_id', 'ingredient_type', 'ingredient_menu_id'],
                'menu_ingredient_unique'
            );
            $table->index(['menu_type', 'menu_id']);
            $table->index(['ingredient_type', 'ingredient_menu_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('menu_ingredients');
    }
};
