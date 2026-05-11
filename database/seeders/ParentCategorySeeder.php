<?php

namespace Database\Seeders;

use App\Models\ParentCategory;
use Illuminate\Database\Seeder;

class ParentCategorySeeder extends Seeder
{
    public function run()
    {
        ParentCategory::firstOrCreate(['name' => 'FOOD']);
        ParentCategory::firstOrCreate(['name' => 'DRINKS']);
    }
}
