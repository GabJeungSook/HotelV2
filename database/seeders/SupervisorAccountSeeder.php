<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SupervisorAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Make sure supervisor role exists
        if (!Role::where('name', 'supervisor')->exists()) {
            Role::create(['name' => 'supervisor']);
        }

        // Create test supervisor account if not exists
        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor@test.com'],
            [
                'name' => 'Test Supervisor',
                'password' => Hash::make('password'),
                'branch_id' => 1, // Change to your branch ID
            ]
        );

        // Assign supervisor role
        if (!$supervisor->hasRole('supervisor')) {
            $supervisor->assignRole('supervisor');
        }
    }
}
