<?php

namespace Tests\Feature\Admin;

use App\Enums\LicenseStatus;
use App\Enums\SettlementStatus;
use App\Models\Admin;
use App\Models\Box;
use App\Models\Expense;
use App\Models\License;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\Withdrawal;
use App\Services\Admin\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Partner clearing (المقاصة).
 *
 * The arithmetic is the whole feature, so most of this file pins the numbers
 * rather than the plumbing - in particular the two properties that are easy to
 * get subtly wrong and impossible to spot by eye afterwards: withdrawals must
 * not be treated as a cost, and the per-line rounding residual must land in
 * `retained` so the reconciliation identity stays exact.
 */
class SettlementTest extends TestCase
{
    use RefreshDatabase;

    private Box $box;

    private License $license;

    protected function setUp(): void
    {
        parent::setUp();

        $package = Package::create(['name' => 'Basic', 'price' => 10, 'device_limit' => 1, 'features' => []]);
        $this->box = Box::create(['box_name' => 'Main', 'box_balance' => 0]);
        $this->license = License::create([
            'license_code' => 'CLEARING',
            'customer_name' => 'Acme',
            'package_id' => $package->id,
            'status' => LicenseStatus::Active,
            'expired_at' => now()->addYear(),
            'customer_balance' => 0,
        ]);
    }

    private function service(): SettlementService
    {
        return app(SettlementService::class);
    }

    private function payment(float $amount, ?string $on = null): Payment
    {
        $payment = Payment::create([
            'license_id' => $this->license->id,
            'box_id' => $this->box->id,
            'amount' => $amount,
        ]);

        if ($on) {
            // Payments have no date column of their own, so the period window
            // is driven by created_at - which means backdating one has to write
            // that column directly.
            $payment->forceFill(['created_at' => Carbon::parse($on)])->saveQuietly();
        }

        return $payment->refresh();
    }

    private function expense(float $amount, ?string $on = null): Expense
    {
        return Expense::create([
            'box_id' => $this->box->id,
            'description' => 'Hosting',
            'amount' => $amount,
            'date' => $on ?? now()->toDateString(),
        ]);
    }

    private function withdrawal(Partner $partner, float $amount, ?string $on = null): Withdrawal
    {
        $withdrawal = Withdrawal::create([
            'box_id' => $this->box->id,
            'partner_id' => $partner->id,
            'amount' => $amount,
        ]);

        if ($on) {
            $withdrawal->forceFill(['created_at' => Carbon::parse($on)])->saveQuietly();
        }

        return $withdrawal->refresh();
    }

    public function test_entitlement_is_a_share_of_profit_and_net_deducts_what_was_drawn(): void
    {
        $alice = Partner::create(['name' => 'Alice', 'percentage' => 60]);
        $bob = Partner::create(['name' => 'Bob', 'percentage' => 40]);

        $this->payment(1000);
        $this->expense(200);
        $this->withdrawal($alice, 100);

        $preview = $this->service()->preview();

        $this->assertSame(1000.0, $preview['totalPayments']);
        $this->assertSame(200.0, $preview['totalExpenses']);
        $this->assertSame(800.0, $preview['distributableProfit']);

        $lines = collect($preview['lines'])->keyBy('partnerName');

        // 800 * 60% = 480, minus the 100 already taken.
        $this->assertSame(480.0, $lines['Alice']['entitlement']);
        $this->assertSame(100.0, $lines['Alice']['drawn']);
        $this->assertSame(380.0, $lines['Alice']['net']);

        // 800 * 40% = 320, nothing drawn.
        $this->assertSame(320.0, $lines['Bob']['entitlement']);
        $this->assertSame(0.0, $lines['Bob']['drawn']);
        $this->assertSame(320.0, $lines['Bob']['net']);

        $this->assertSame(700.0, $preview['totalNet']);
        $this->assertSame(0.0, $preview['retained']);
        $this->assertTrue($preview['reconciles']);

        $this->assertSame($bob->id, $lines['Bob']['partnerId']);
    }

    /**
     * The property the whole design turns on: a withdrawal is a distribution of
     * profit, not a cost of earning it. If it were subtracted from
     * distributable profit, Bob's entitlement would drop because Alice took
     * money out - charging him for her payout.
     */
    public function test_one_partners_withdrawal_does_not_reduce_another_partners_entitlement(): void
    {
        $alice = Partner::create(['name' => 'Alice', 'percentage' => 50]);
        Partner::create(['name' => 'Bob', 'percentage' => 50]);

        $this->payment(1000);

        $before = collect($this->service()->preview()['lines'])->firstWhere('partnerName', 'Bob');

        $this->withdrawal($alice, 400);

        $after = collect($this->service()->preview()['lines'])->firstWhere('partnerName', 'Bob');

        $this->assertSame(500.0, $before['entitlement']);
        $this->assertSame(500.0, $after['entitlement'], 'Bob was charged for Alice\'s withdrawal.');
        $this->assertSame(500.0, $after['net']);
    }

    /**
     * Three-way split of 100 rounds to 33.33 each, leaving 0.01 unallocated.
     * That residual must land in `retained`, not vanish - otherwise the totals
     * on the page silently stop adding up.
     */
    public function test_rounding_residual_lands_in_retained_so_the_identity_stays_exact(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            Partner::create(['name' => $name, 'percentage' => 33.33]);
        }

        $this->payment(100);

        $preview = $this->service()->preview();

        $this->assertSame(100.0, $preview['distributableProfit']);
        $this->assertSame(99.99, $preview['totalEntitlement']);
        $this->assertSame(0.01, $preview['retained']);

        // sum(net) + retained == distributable - sum(drawn)
        $this->assertEqualsWithDelta(
            $preview['totalNet'] + $preview['retained'],
            $preview['distributableProfit'] - $preview['totalDrawn'],
            0.001
        );
        $this->assertTrue($preview['reconciles']);
    }

    public function test_percentages_under_one_hundred_leave_the_remainder_retained(): void
    {
        Partner::create(['name' => 'Solo', 'percentage' => 70]);
        $this->payment(1000);

        $preview = $this->service()->preview();

        $this->assertSame(700.0, $preview['totalEntitlement']);
        $this->assertSame(300.0, $preview['retained']);
        $this->assertSame(70.0, $preview['percentageTotal']);
    }

    public function test_confirming_closes_the_period_and_the_next_run_excludes_settled_movement(): void
    {
        $alice = Partner::create(['name' => 'Alice', 'percentage' => 100]);

        $this->payment(500, '2026-01-10');
        $this->withdrawal($alice, 100, '2026-01-15');

        $first = $this->service()->create(['periodTo' => '2026-01-31', 'confirm' => true]);

        $this->assertTrue($first->isConfirmed());
        $this->assertSame('500.00', $first->total_payments);
        $this->assertSame('400.00', $first->total_net);

        // A later payment belongs to the NEXT period only.
        $this->payment(200, '2026-02-05');

        $next = $this->service()->preview();

        $this->assertSame('2026-02-01', $next['periodFrom']->toDateString());
        $this->assertSame(200.0, $next['totalPayments'], 'January was counted twice.');
        $this->assertSame(0.0, $next['totalDrawn'], 'January\'s withdrawal leaked into February.');
    }

    public function test_a_draft_does_not_close_the_period(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(500, '2026-01-10');

        $draft = $this->service()->create(['periodTo' => '2026-01-31']);

        $this->assertSame(SettlementStatus::Draft, $draft->status);
        $this->assertNull($draft->confirmed_at);

        // Still open: the period start has not moved past January.
        $this->assertTrue($this->service()->nextPeriodStart()->lte(Carbon::parse('2026-01-10')));
    }

    public function test_only_one_draft_may_exist_at_a_time(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(100, '2026-01-10');

        $this->service()->create(['periodTo' => '2026-01-31']);

        $this->expectExceptionMessage(__('توجد مقاصة مسودّة بالفعل. أكّدها أو احذفها قبل بدء مقاصة جديدة.'));
        $this->service()->create(['periodTo' => '2026-02-28']);
    }

    public function test_a_confirmed_run_cannot_be_deleted_but_a_draft_can(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(100, '2026-01-10');

        $draft = $this->service()->create(['periodTo' => '2026-01-31']);
        $this->service()->deleteDraft($draft);
        $this->assertDatabaseCount('settlements', 0);

        $confirmed = $this->service()->create(['periodTo' => '2026-01-31', 'confirm' => true]);

        $this->expectExceptionMessage(__('لا يمكن حذف مقاصة مؤكّدة.'));
        $this->service()->deleteDraft($confirmed);
    }

    /**
     * A confirmed run is a document the partner was shown. Editing their share
     * afterwards must not rewrite what it says.
     */
    public function test_confirmed_lines_keep_their_snapshot_when_the_partner_changes(): void
    {
        $alice = Partner::create(['name' => 'Alice', 'percentage' => 50]);
        Partner::create(['name' => 'Bob', 'percentage' => 50]);
        $this->payment(1000, '2026-01-10');

        $settlement = $this->service()->create(['periodTo' => '2026-01-31', 'confirm' => true]);

        $alice->update(['name' => 'Alice Renamed', 'percentage' => 90]);

        $line = $settlement->fresh()->lines->firstWhere('partner_id', $alice->id);

        $this->assertSame('Alice', $line->partner_name);
        $this->assertSame('50.00', $line->percentage);
        $this->assertSame('500.00', $line->entitlement);
    }

    public function test_references_are_sequential_within_the_year(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(100, '2026-01-10');

        $first = $this->service()->create(['periodTo' => '2026-01-31', 'confirm' => true]);
        $second = $this->service()->create(['periodTo' => '2026-02-28', 'confirm' => true]);

        $this->assertSame('MQ-2026-0001', $first->reference);
        $this->assertSame('MQ-2026-0002', $second->reference);
    }

    public function test_a_period_end_before_the_open_period_start_is_rejected(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(100, '2026-01-10');
        $this->service()->create(['periodTo' => '2026-01-31', 'confirm' => true]);

        $this->expectExceptionMessage(__('تاريخ نهاية الفترة أقدم من بداية الفترة المفتوحة.'));
        $this->service()->create(['periodTo' => '2026-01-15']);
    }

    public function test_expenses_are_dated_by_their_own_column_not_created_at(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(1000, '2026-03-05');

        // Recorded today, but back-dated into the period being settled.
        $this->expense(300, '2026-03-10');

        $preview = $this->service()->preview(Carbon::parse('2026-03-31'));

        $this->assertSame(300.0, $preview['totalExpenses']);
        $this->assertSame(700.0, $preview['distributableProfit']);
    }

    /**
     * Regression: `expenses.date` is cast to `date`, so Eloquent stores it as
     * "Y-m-d H:i:s". An inclusive BETWEEN upper bound of "2026-03-31" compared
     * as a string against "2026-03-31 00:00:00" loses, which dropped every
     * movement on the final day of a period. Caught by a failing assertion, not
     * by review - so it is pinned here at both edges of the window.
     */
    public function test_movement_on_the_first_and_last_day_of_the_period_is_included(): void
    {
        $alice = Partner::create(['name' => 'Alice', 'percentage' => 100]);

        $this->payment(500, '2026-03-01 00:00:01');   // first day
        $this->payment(300, '2026-03-31 23:59:59');   // last day
        $this->expense(100, '2026-03-01');            // first day
        $this->expense(200, '2026-03-31');            // last day
        $this->withdrawal($alice, 50, '2026-03-31 23:59:58');

        $preview = $this->service()->preview(Carbon::parse('2026-03-31'));

        $this->assertSame(800.0, $preview['totalPayments'], 'A payment on a boundary day was dropped.');
        $this->assertSame(300.0, $preview['totalExpenses'], 'An expense on a boundary day was dropped.');
        $this->assertSame(50.0, $preview['totalDrawn'], 'A withdrawal on the last day was dropped.');
        $this->assertSame(500.0, $preview['distributableProfit']);
    }

    /** The window is half-open, so the day AFTER period_to must not leak in. */
    public function test_movement_after_the_period_end_is_excluded(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);

        $this->payment(500, '2026-03-31 12:00:00');
        $this->payment(999, '2026-04-01 00:00:00');
        $this->expense(777, '2026-04-01');

        $preview = $this->service()->preview(Carbon::parse('2026-03-31'));

        $this->assertSame(500.0, $preview['totalPayments'], 'April leaked into the March period.');
        $this->assertSame(0.0, $preview['totalExpenses']);
    }

    public function test_the_clearing_page_renders_and_a_run_can_be_created_over_http(): void
    {
        $admin = Admin::factory()->create();
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(400);
        $this->withdrawal(Partner::first(), 150);

        $this->actingAs($admin, 'admin')->get('/admin/settlements')->assertOk();

        $this->actingAs($admin, 'admin')
            ->post('/admin/settlements', [
                'periodTo' => now()->toDateString(),
                'notes' => 'Q1 clearing',
                'confirm' => '1',
            ])
            ->assertRedirect();

        $settlement = Settlement::firstOrFail();

        $this->assertSame($admin->id, $settlement->admin_id);
        $this->assertSame('Q1 clearing', $settlement->notes);
        $this->assertSame('250.00', $settlement->total_net);
        $this->assertTrue($settlement->isConfirmed());

        $this->actingAs($admin, 'admin')->get("/admin/settlements/{$settlement->id}")->assertOk();
    }

    public function test_a_future_period_end_is_rejected_by_validation(): void
    {
        $admin = Admin::factory()->create();
        Partner::create(['name' => 'Alice', 'percentage' => 100]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/settlements', ['periodTo' => now()->addWeek()->toDateString()])
            ->assertSessionHasErrors('periodTo');
    }

    public function test_confirming_a_draft_over_http_closes_it(): void
    {
        $admin = Admin::factory()->create();
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(100, '2026-01-10');

        $draft = $this->service()->create(['periodTo' => '2026-01-31']);

        $this->actingAs($admin, 'admin')
            ->post("/admin/settlements/{$draft->id}/confirm")
            ->assertRedirect();

        $this->assertTrue($draft->fresh()->isConfirmed());
        $this->assertNotNull($draft->fresh()->confirmed_at);
    }

    /**
     * An open draft is the ONLY thing that blocks running a new clearing, so
     * the preview has to hand back the draft itself and not merely a boolean -
     * otherwise the page announces the blocker and cannot link to it.
     */
    public function test_the_preview_hands_back_the_open_draft_itself(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(100, '2026-01-10');

        $this->assertNull($this->service()->preview()['openDraft']);

        $draft = $this->service()->create(['periodTo' => '2026-01-31']);

        $preview = $this->service()->preview();

        $this->assertTrue($preview['hasOpenDraft']);
        $this->assertSame($draft->id, $preview['openDraft']?->id);
    }

    /**
     * Partner percentages summing over 100 mean the run would hand out more
     * than the period earned, leaving `retained` negative - the platform owing
     * money it never made. The page warns about it, but a warning is not a
     * guard: the run was still committable, and a confirmed settlement cannot
     * be edited or deleted afterwards.
     *
     * Almost always this is a data-entry slip in the partner list, which is why
     * the message says what the total actually is.
     */
    public function test_a_run_is_refused_when_partner_percentages_exceed_one_hundred(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 70]);
        Partner::create(['name' => 'Bob', 'percentage' => 55]);
        $this->payment(1000, '2026-01-10');

        $this->expectException(\RuntimeException::class);

        try {
            $this->service()->create(['periodTo' => '2026-01-31']);
        } finally {
            $this->assertSame(0, Settlement::count(), 'A refused run must leave nothing behind.');
        }
    }

    /** Exactly 100% is the normal, fully-allocated case and must still run. */
    public function test_percentages_totalling_exactly_one_hundred_are_allowed(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 60]);
        Partner::create(['name' => 'Bob', 'percentage' => 40]);
        $this->payment(1000, '2026-01-10');

        $settlement = $this->service()->create(['periodTo' => '2026-01-31']);

        $this->assertSame('1000.00', $settlement->total_entitlement);
        $this->assertSame('0.00', $settlement->retained);
    }

    /** Under 100% is also normal - the remainder is simply retained. */
    public function test_percentages_under_one_hundred_still_run(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 30]);
        $this->payment(1000, '2026-01-10');

        $settlement = $this->service()->create(['periodTo' => '2026-01-31']);

        $this->assertSame('300.00', $settlement->total_entitlement);
        $this->assertSame('700.00', $settlement->retained);
    }

    /** A confirmed run is not an open draft and must not block the next one. */
    public function test_a_confirmed_run_leaves_no_open_draft(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(100, '2026-01-10');

        $this->service()->create(['periodTo' => '2026-01-31', 'confirm' => true]);

        $preview = $this->service()->preview();

        $this->assertFalse($preview['hasOpenDraft']);
        $this->assertNull($preview['openDraft']);
    }

    /** The clearing page must link to the draft, not just mention it. */
    public function test_the_clearing_page_links_to_the_open_draft(): void
    {
        Partner::create(['name' => 'Alice', 'percentage' => 100]);
        $this->payment(100, '2026-01-10');
        $draft = $this->service()->create(['periodTo' => '2026-01-31']);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin/settlements')
            ->assertOk()
            ->assertSee(route('admin.settlements.show', $draft), false)
            ->assertSee($draft->reference);
    }
}
