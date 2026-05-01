<?php

namespace Tests\Feature\UrgentBugFixes;

use App\Http\Livewire\BackOffice\Reports\Guest as GuestReport;
use App\Models\Branch;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers C6/C7/C8/C10 fixes — multi-tenant isolation + finishCleaning ghost.
 *
 *   C6  Roomboy finishCleaning aborts if a room flipped to Occupied
 *   C7  API OccupiedRoom + QrRoom reject foreign branch_id
 *   C8  BackOffice Reports/Guest case 3 scoped to own branch
 *   C10 Roomboy startCleaning rejects foreign branch room
 *
 * These are stand-alone happy-path tests; race-condition edges are covered
 * by the existing CleanupTemporaryKioskTest + KioskBatchTest suites.
 */
class TenantAndRaceProtectionTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function backoffice_guest_report_case_3_only_returns_own_branch()
    {
        Role::findOrCreate('back_office');

        $branchA = Branch::create(['name' => 'A ' . uniqid(), 'kiosk_time_limit' => 10]);
        $branchB = Branch::create(['name' => 'B ' . uniqid(), 'kiosk_time_limit' => 10]);

        $userA = User::create([
            'name' => 'Back Office A',
            'email' => 'a' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'branch_id' => $branchA->id,
            'branch_name' => $branchA->name,
        ]);
        $userA->assignRole('back_office');

        // One guest in each branch with a transaction_type_id=6 transaction
        // (the case-3 filter). The leak would have returned BOTH; the fix
        // limits to branchA only.
        $guestA = Guest::create([
            'branch_id' => $branchA->id,
            'name' => 'Guest A',
            'contact' => 'N/A',
            'qr_code' => 'TEST-A-' . uniqid(),
            'room_id' => 1,
            'rate_id' => 1,
            'type_id' => 1,
            'static_amount' => 0,
        ]);
        $guestB = Guest::create([
            'branch_id' => $branchB->id,
            'name' => 'Guest B',
            'contact' => 'N/A',
            'qr_code' => 'TEST-B-' . uniqid(),
            'room_id' => 1,
            'rate_id' => 1,
            'type_id' => 1,
            'static_amount' => 0,
        ]);

        Transaction::create([
            'branch_id' => $branchA->id,
            'guest_id' => $guestA->id,
            'transaction_type_id' => 6,
            'payable_amount' => 100,
            'paid_amount' => 100,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'description' => 'A txn',
            'remarks' => 'A',
            'shift' => 'AM',
            'assigned_frontdesk_id' => '[1,"N/A"]',
        ]);
        Transaction::create([
            'branch_id' => $branchB->id,
            'guest_id' => $guestB->id,
            'transaction_type_id' => 6,
            'payable_amount' => 100,
            'paid_amount' => 100,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'description' => 'B txn',
            'remarks' => 'B',
            'shift' => 'AM',
            'assigned_frontdesk_id' => '[1,"N/A"]',
        ]);

        $this->actingAs($userA);

        $report = new GuestReport();
        $report->type = 3;
        $report->date = null;

        $rows = $report->loadQuery();

        $ids = $rows->pluck('id')->all();
        $this->assertContains($guestA->id, $ids, 'Own-branch guest must appear.');
        $this->assertNotContains($guestB->id, $ids, 'Foreign-branch guest must NOT leak.');
    }

    /** @test */
    public function api_occupied_rooms_rejects_foreign_branch()
    {
        Role::findOrCreate('frontdesk');

        $branchA = Branch::create(['name' => 'A api ' . uniqid(), 'kiosk_time_limit' => 10]);
        $branchB = Branch::create(['name' => 'B api ' . uniqid(), 'kiosk_time_limit' => 10]);

        $userA = User::create([
            'name' => 'Front A',
            'email' => 'fa' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'branch_id' => $branchA->id,
            'branch_name' => $branchA->name,
        ]);
        $userA->assignRole('frontdesk');

        // sanctum-style auth via actingAs; the controller reads auth()->user()
        $this->actingAs($userA);

        $controller = new \App\Http\Controllers\Api\OccupiedRoomController();

        $response = $controller->occupiedRooms($branchB->id);

        $payload = json_decode($response->getContent(), true);
        $this->assertEquals(403, $response->getStatusCode(), 'Foreign branch must be 403.');
        $this->assertStringContainsString('branch mismatch', strtolower($payload['message'] ?? ''));
    }

    /** @test */
    public function api_qr_room_rejects_foreign_branch()
    {
        Role::findOrCreate('frontdesk');

        $branchA = Branch::create(['name' => 'A qr ' . uniqid(), 'kiosk_time_limit' => 10]);
        $branchB = Branch::create(['name' => 'B qr ' . uniqid(), 'kiosk_time_limit' => 10]);

        $userA = User::create([
            'name' => 'Front A qr',
            'email' => 'fq' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'branch_id' => $branchA->id,
            'branch_name' => $branchA->name,
        ]);
        $userA->assignRole('frontdesk');
        $this->actingAs($userA);

        $controller = new \App\Http\Controllers\Api\QrRoomController();

        $request = new \Illuminate\Http\Request();
        $request->merge(['branch_id' => $branchB->id]);

        $response = $controller->getRoomByQr('SOMEQR', $request);

        $payload = json_decode($response->getContent(), true);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('branch mismatch', strtolower($payload['message'] ?? ''));
    }

    /** @test */
    public function roomboy_finish_cleaning_aborts_when_room_is_now_occupied()
    {
        // C6 ghost-protection: a frontdesk took the room mid-cleaning
        // (status flipped to Occupied). finishCleaning must NOT overwrite
        // it back to Available, otherwise we end up with a "ghost room"
        // (Available + active CheckinDetail).
        Role::findOrCreate('roomboy');

        $branch = Branch::create(['name' => 'C6 ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $floor = Floor::create(['branch_id' => $branch->id, 'number' => 1]);
        $room = Room::create([
            'branch_id' => $branch->id,
            'floor_id' => $floor->id,
            'type_id' => $type->id,
            'number' => '1',
            'status' => 'Occupied',
            'is_priority' => true,
            'started_cleaning_at' => now()->subMinutes(5),
            'time_to_clean' => now()->addMinutes(10),
        ]);

        $roomboy = User::create([
            'name' => 'Roomboy',
            'email' => 'rb' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
        ]);
        $roomboy->assignRole('roomboy');
        $this->actingAs($roomboy);

        Livewire::test(\App\Http\Livewire\Roomboy\Index::class)
            ->call('finishCleaning', $room->id);

        $room->refresh();
        $this->assertEquals('Occupied', $room->status, 'Roomboy must NOT flip Occupied → Available.');
    }

    /** @test */
    public function roomboy_start_cleaning_rejects_room_from_another_branch()
    {
        Role::findOrCreate('roomboy');

        $branchA = Branch::create(['name' => 'A rb ' . uniqid(), 'kiosk_time_limit' => 10]);
        $branchB = Branch::create(['name' => 'B rb ' . uniqid(), 'kiosk_time_limit' => 10]);
        $typeB = Type::create(['branch_id' => $branchB->id, 'name' => 'Test']);
        $floorB = Floor::create(['branch_id' => $branchB->id, 'number' => 1]);
        $foreignRoom = Room::create([
            'branch_id' => $branchB->id,
            'floor_id' => $floorB->id,
            'type_id' => $typeB->id,
            'number' => '1',
            'status' => 'Uncleaned',
            'is_priority' => true,
        ]);

        $roomboyA = User::create([
            'name' => 'Roomboy A',
            'email' => 'rb-a' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'branch_id' => $branchA->id,
            'branch_name' => $branchA->name,
        ]);
        $roomboyA->assignRole('roomboy');
        $this->actingAs($roomboyA);

        Livewire::test(\App\Http\Livewire\Roomboy\Index::class)
            ->call('startCleaning', $foreignRoom->id);

        $foreignRoom->refresh();
        $this->assertEquals('Uncleaned', $foreignRoom->status, 'Foreign branch room must not change.');
        $this->assertNull($foreignRoom->started_cleaning_at, 'Foreign branch room must not be touched.');
    }
}
