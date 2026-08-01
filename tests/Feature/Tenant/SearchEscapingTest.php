<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Support\SearchTerm;
use Tests\TenantTestCase;

class SearchEscapingTest extends TenantTestCase
{
    private int $phoneSeed = 0;

    private function customer(string $name, ?string $phone = null): Customer
    {
        return Customer::factory()->create([
            'name' => $name,
            'phone' => $phone ?? '05'.str_pad((string) ++$this->phoneSeed, 8, '0', STR_PAD_LEFT),
        ]);
    }

    public function test_a_wildcard_is_escaped_and_the_escape_character_is_doubled(): void
    {
        $this->assertSame('%a!%b%', SearchTerm::pattern('a%b'));
        $this->assertSame('%a!_b%', SearchTerm::pattern('a_b'));
        $this->assertSame('%a!!b%', SearchTerm::pattern('a!b'));
    }

    public function test_a_term_mixing_the_escape_character_and_a_wildcard_survives(): void
    {
        $this->assertSame('%a!!!%b%', SearchTerm::pattern('a!%b'));
    }

    public function test_an_ordinary_term_is_left_alone(): void
    {
        $this->assertSame('%كباب%', SearchTerm::pattern('كباب'));
    }

    public function test_a_percent_sign_matches_only_a_literal_percent_sign(): void
    {
        $this->customer('خصم 50%');
        $this->customer('أحمد');
        $this->customer('سارة');

        $matches = Customer::query()->whereSearch(['name', 'phone'], '%')->pluck('name');

        $this->assertSame(['خصم 50%'], $matches->all());
    }

    public function test_an_underscore_matches_only_a_literal_underscore(): void
    {
        $role = Role::factory()->create();
        TeamMember::factory()->create(['email' => 'abu_ali@example.test', 'role_id' => $role->id]);
        TeamMember::factory()->create(['email' => 'abuXali@example.test', 'role_id' => $role->id]);

        $matches = TeamMember::query()->whereSearch(['name', 'email'], 'abu_ali')->pluck('email');

        $this->assertSame(['abu_ali@example.test'], $matches->all());
    }

    public function test_the_escape_character_can_itself_be_searched_for(): void
    {
        $this->customer('عاجل!');
        $this->customer('عاجل');

        $matches = Customer::query()->whereSearch(['name', 'phone'], 'عاجل!')->pluck('name');

        $this->assertSame(['عاجل!'], $matches->all());
    }

    public function test_a_plain_term_still_matches_as_a_substring(): void
    {
        $this->customer('أحمد علي');
        $this->customer('سارة');

        $matches = Customer::query()->whereSearch(['name', 'phone'], 'حمد')->pluck('name');

        $this->assertSame(['أحمد علي'], $matches->all());
    }

    public function test_the_columns_are_searched_as_alternatives(): void
    {
        $this->customer('أحمد', '0501234567');
        $this->customer('سارة', '0559999999');

        $this->assertSame(
            ['سارة'],
            Customer::query()->whereSearch(['name', 'phone'], '0559')->pluck('name')->all(),
        );
    }

    public function test_the_or_group_does_not_swallow_a_neighbouring_filter(): void
    {
        Account::factory()->create(['holder_name' => 'بنك الراجحي', 'active' => true]);
        Account::factory()->create(['holder_name' => 'بنك الأهلي', 'active' => false]);

        $matches = Account::query()
            ->whereSearch(['holder_name', 'iban'], 'بنك')
            ->where('active', true)
            ->pluck('holder_name');

        $this->assertSame(['بنك الراجحي'], $matches->all());
    }

    public function test_an_empty_term_is_a_no_op(): void
    {
        $this->customer('أحمد');
        $this->customer('سارة');

        $this->assertSame(2, Customer::query()->whereSearch(['name', 'phone'], null)->count());
        $this->assertSame(2, Customer::query()->whereSearch(['name', 'phone'], '   ')->count());
    }

    public function test_the_compiled_sql_carries_the_escape_clause(): void
    {
        $sql = Customer::query()->whereSearch(['name', 'phone'], 'x')->toSql();

        $this->assertStringContainsString("escape '!'", $sql);
        $this->assertSame(2, substr_count($sql, "escape '!'"), 'Each column needs its own clause.');
    }
}
