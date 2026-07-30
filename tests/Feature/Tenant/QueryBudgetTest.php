<?php

namespace Tests\Feature\Tenant;

use App\Enums\PaymentMethod;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\TransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * How many queries each page costs, and - the part that matters - whether that
 * number grows with the amount of data on it.
 *
 * An N+1 is invisible in every other kind of test: the page renders, the totals
 * are right, and the suite is green. It only shows up as a shop with a busy
 * month waiting on a screen that was instant with demo data. Counting the
 * queries at two different row counts is the only way to see it from here.
 */
class QueryBudgetTest extends TenantTestCase
{
    private TeamMember $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $roles = $this->seedStandardRoles();
        $this->owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    private function placeOrders(int $count): void
    {
        $item = MenuItem::factory()->create(['price' => '10.00']);

        for ($i = 0; $i < $count; $i++) {
            app(TransferService::class)->create([
                'customer' => "عميل {$i}",
                'customerPhone' => '05990000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'paymentMethod' => PaymentMethod::Cash,
                'items' => [['itemId' => $item->id, 'quantity' => 1]],
            ]);
        }
    }

    private function queriesFor(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->owner, 'tenant')->get($uri)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'history' => ['/history'],
            'customers' => ['/customers'],
            'reports' => ['/reports'],
            'balance' => ['/balance'],
        ];
    }

    /**
     * The query count must not grow with the number of rows rendered. Five
     * orders and twenty-five orders have to cost the same, or something is
     * being fetched per row.
     */
    #[DataProvider('pages')]
    public function test_a_page_costs_the_same_whether_it_shows_five_rows_or_twenty_five(string $uri): void
    {
        $this->placeOrders(5);
        $withFive = $this->queriesFor($uri);

        $this->placeOrders(20);
        $withTwentyFive = $this->queriesFor($uri);

        $this->assertSame(
            $withFive,
            $withTwentyFive,
            sprintf(
                '%s issued %d queries for 5 orders and %d for 25 - the cost grows with the data, which is an N+1.',
                $uri,
                $withFive,
                $withTwentyFive,
            ),
        );
    }
}
