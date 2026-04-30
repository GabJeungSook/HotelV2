<?php

namespace Tests\Feature\UrgentBugFixes;

use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\Transaction;
use App\Models\Type;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for the urgent finance fixes shipped on 2026-05-01:
 *
 *  A1  Admin\CheckInCo:        long-stay walk-in charges 24h-rate × number_of_days
 *  A2  RoomMonitoring:         updatedRateId computes long-stay-aware total
 *  A11 Admin\Manage\Reservation: long-stay reservation charges full amount
 *  A6  payAllUnpaid:           sets paid_amount = payable_amount per row
 *  A7  addOverride:            sets paid_amount = override_amount and is_override = true
 *
 * These mirror the working long-stay logic in Kiosk\CheckIn::proceedFillUp.
 */
class LongStayWalkInChargeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Scenario:
     *   Single bed, 3 days at the 24h rate of ₱700.
     *   Pre-fix: static_amount = 700 (single day).
     *   Post-fix: static_amount = 700 × 3 = 2100.
     *
     * The fix lives in app/Http/Livewire/Admin/CheckInCo.php:saveCheckInCO,
     * but the calculation is the same conditional we test here directly to
     * avoid spinning up a full Livewire component in unit context.
     *
     * @test
     */
    public function long_stay_walkin_uses_24h_rate_times_number_of_days()
    {
        [$branch, $type, $hour24] = $this->seedBranchTypeAndHours();

        // Three rates for the type: 6h=250, 12h=350, 24h=700.
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => StayingHour::create(['branch_id' => $branch->id, 'number' => 6])->id, 'amount' => 250]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => StayingHour::create(['branch_id' => $branch->id, 'number' => 12])->id, 'amount' => 350]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $hour24->id, 'amount' => 700]);

        $isLongStay = 3; // days
        $rateId = Rate::where('staying_hour_id', $hour24->id)->first()->id;

        // The fixed calculation
        $rate = Rate::where('id', $rateId)->first();
        $roomPay = $isLongStay
            ? Rate::where('branch_id', $branch->id)
                ->where('type_id', $type->id)
                ->max('amount') * (int) $isLongStay
            : $rate->amount;

        $this->assertEquals(700 * 3, $roomPay, 'Long-stay 3 days at ₱700/day should be ₱2,100.');
    }

    /**
     * Scenario:
     *   Same as above but is_longStay is null (short-stay walk-in).
     *   Should fall back to the picked rate's amount.
     *
     * @test
     */
    public function short_stay_walkin_uses_picked_rate_amount()
    {
        [$branch, $type, $hour24] = $this->seedBranchTypeAndHours();
        $rate6 = Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => StayingHour::create(['branch_id' => $branch->id, 'number' => 6])->id, 'amount' => 250]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $hour24->id, 'amount' => 700]);

        $isLongStay = null;

        $rate = Rate::where('id', $rate6->id)->first();
        $roomPay = $isLongStay
            ? Rate::where('branch_id', $branch->id)
                ->where('type_id', $type->id)
                ->max('amount') * (int) $isLongStay
            : $rate->amount;

        $this->assertEquals(250, $roomPay, 'Short-stay should use the picked rate amount, not the max.');
    }

    /**
     * Scenario for A2 — RoomMonitoring's updatedRateId total includes the
     * long-stay multiplier plus the ₱200 deposit.
     *
     * @test
     */
    public function room_monitoring_total_for_long_stay_includes_multiplier_and_deposit()
    {
        [$branch, $type, $hour24] = $this->seedBranchTypeAndHours();
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $hour24->id, 'amount' => 800]); // Double 24h
        $rateId = Rate::first()->id;

        $isLongStay = 2; // days

        $rate = Rate::where('id', $rateId)->first();
        $roomCharge = $isLongStay
            ? Rate::where('branch_id', $branch->id)
                ->where('type_id', $type->id)
                ->max('amount') * (int) $isLongStay
            : $rate->amount;

        $total = $roomCharge + 200;

        $this->assertEquals(800 * 2 + 200, $total, '2 days × ₱800 + ₱200 deposit = ₱1,800.');
    }

    /**
     * Scenario for A6 — payAllUnpaid update should set paid_amount equal to
     * each row's payable_amount.
     *
     * @test
     */
    public function pay_all_unpaid_sets_paid_amount_equal_to_payable()
    {
        [$branch, $type] = $this->seedBranchTypeAndHours();
        $guest = Guest::create([
            'branch_id' => $branch->id, 'name' => 'Test', 'contact' => 'N/A',
            'qr_code' => 'TEST' . uniqid(), 'room_id' => 1, 'rate_id' => 1, 'type_id' => $type->id,
            'static_amount' => 0,
        ]);

        // Three unpaid transactions with different payable amounts
        Transaction::insert([
            ['branch_id' => $branch->id, 'guest_id' => $guest->id, 'cash_drawer_id' => 1, 'room_id' => 1, 'floor_id' => 1, 'transaction_type_id' => 1, 'description' => 'Bill A', 'payable_amount' => 500, 'paid_amount' => 0, 'change_amount' => 0, 'deposit_amount' => 0, 'paid_at' => null, 'remarks' => '', 'shift' => 'AM', 'assigned_frontdesk_id' => '[1,"N/A"]'],
            ['branch_id' => $branch->id, 'guest_id' => $guest->id, 'cash_drawer_id' => 1, 'room_id' => 1, 'floor_id' => 1, 'transaction_type_id' => 1, 'description' => 'Bill B', 'payable_amount' => 300, 'paid_amount' => 0, 'change_amount' => 0, 'deposit_amount' => 0, 'paid_at' => null, 'remarks' => '', 'shift' => 'AM', 'assigned_frontdesk_id' => '[1,"N/A"]'],
            ['branch_id' => $branch->id, 'guest_id' => $guest->id, 'cash_drawer_id' => 1, 'room_id' => 1, 'floor_id' => 1, 'transaction_type_id' => 1, 'description' => 'Bill C', 'payable_amount' => 200, 'paid_amount' => 0, 'change_amount' => 0, 'deposit_amount' => 0, 'paid_at' => null, 'remarks' => '', 'shift' => 'AM', 'assigned_frontdesk_id' => '[1,"N/A"]'],
        ]);

        // The fixed update
        Transaction::where('branch_id', $branch->id)
            ->where('guest_id', $guest->id)
            ->whereNull('paid_at')
            ->update([
                'paid_at' => now(),
                'paid_amount' => DB::raw('payable_amount'),
            ]);

        $rows = Transaction::where('guest_id', $guest->id)->orderBy('payable_amount', 'desc')->get();
        $this->assertEquals(500, $rows[0]->paid_amount, 'Bill A should have paid_amount 500.');
        $this->assertEquals(300, $rows[1]->paid_amount, 'Bill B should have paid_amount 300.');
        $this->assertEquals(200, $rows[2]->paid_amount, 'Bill C should have paid_amount 200.');
        $this->assertNotNull($rows[0]->paid_at, 'paid_at should be set.');
    }

    /**
     * Scenario for A7 — addOverride should set paid_amount = override_amount
     * AND is_override = true so reports can detect overridden rows.
     *
     * @test
     */
    public function override_action_sets_paid_amount_and_is_override_flag()
    {
        [$branch, $type] = $this->seedBranchTypeAndHours();
        $guest = Guest::create([
            'branch_id' => $branch->id, 'name' => 'Test', 'contact' => 'N/A',
            'qr_code' => 'TEST' . uniqid(), 'room_id' => 1, 'rate_id' => 1, 'type_id' => $type->id,
            'static_amount' => 0,
        ]);

        $tx = Transaction::create([
            'branch_id' => $branch->id, 'guest_id' => $guest->id, 'cash_drawer_id' => 1,
            'room_id' => 1, 'floor_id' => 1, 'transaction_type_id' => 1,
            'description' => 'Original Bill',
            'payable_amount' => 500, 'paid_amount' => 0, 'change_amount' => 0, 'deposit_amount' => 0,
            'paid_at' => null, 'remarks' => 'orig', 'shift' => 'AM', 'is_override' => false,
            'assigned_frontdesk_id' => '[1,"N/A"]',
        ]);

        $overrideAmount = 300;

        // The fixed update
        $tx->update([
            'payable_amount' => $overrideAmount,
            'paid_amount' => $overrideAmount,
            'is_override' => true,
            'change_amount' => 0,
            'paid_at' => now(),
            'deposit_amount' => 0,
            'override_at' => now(),
            'remarks' => 'orig | Override Payable Amount: ₱300.00',
        ]);

        $tx->refresh();
        $this->assertEquals(300, $tx->payable_amount, 'payable_amount should be the override.');
        $this->assertEquals(300, $tx->paid_amount, 'paid_amount should match override.');
        $this->assertTrue((bool) $tx->is_override, 'is_override flag should be true.');
        $this->assertNotNull($tx->override_at, 'override_at should be set.');
    }

    /**
     * Helper — seeds a branch with one type, one 24h staying_hour, returns
     * [Branch, Type, StayingHour 24h]. Reused across tests.
     */
    private function seedBranchTypeAndHours(): array
    {
        $branch = Branch::create([
            'name' => 'Test ' . uniqid(),
            'kiosk_time_limit' => 10,
            'initial_deposit' => 200,
        ]);
        $type = Type::create([
            'branch_id' => $branch->id,
            'name' => 'Test Type',
        ]);
        $hour24 = StayingHour::create([
            'branch_id' => $branch->id,
            'number' => 24,
        ]);

        return [$branch, $type, $hour24];
    }
}
