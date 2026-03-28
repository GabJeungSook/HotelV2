<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call(RoleSeeder::class);
        $this->call(BranchSeeder::class);
        $this->call(AlmaResidenceSeeder::class);
        $this->call(TransactionTypeSeeder::class);
        $this->call(RateSeeder::class);
        $this->call(AdditionalRoleSeeder::class);
        $this->call(AdditionalUserSeeder::class);
        $this->call(AdditionalStayingHourSeeder::class);
    }
}
