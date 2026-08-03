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

    public static function pages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'history' => ['/history'],
            'customers' => ['/customers'],
            'reports' => ['/reports'],
            'balance' => ['/balance'],

            // The margin screens aggregate over every sale in the period, so
            // they are the likeliest place for a per-row query to creep in.
            'margins' => ['/margins'],
            'margins by category' => ['/margins?view=categories'],
            'margins abc' => ['/margins?view=abc'],
            'pricing' => ['/margins/pricing'],
            'valuation' => ['/margins/valuation'],
        ];
    }

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
