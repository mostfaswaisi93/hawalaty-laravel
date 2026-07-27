<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Setting;
use App\Models\Tenant\TeamMember;
use App\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * First tests against routes/api.php.
 *
 * Focused on the platform-settings endpoint because it is where the API could
 * quietly diverge from the web app: the admin form validates each setting with
 * its declared type, and PUT /api/v1/admin/settings/{key} has to enforce the
 * same rules and invalidate the same cache, or the two interfaces disagree
 * about what a valid `trial_days` is.
 */
class AdminSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public function test_a_guest_cannot_read_platform_settings(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();
    }

    /**
     * Admin and TeamMember both use HasApiTokens against the same token table,
     * so a valid bearer token alone does not prove platform staff.
     */
    public function test_a_tenant_member_token_is_rejected_by_the_admin_guard(): void
    {
        Sanctum::actingAs(new TeamMember(['name' => 'Not an admin']), ['*']);

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
    }

    public function test_an_admin_can_list_settings(): void
    {
        $this->actingAsAdmin();
        Setting::create(['key' => 'site_name', 'value' => 'Hawalaty Platform']);

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonFragment(['key' => 'site_name']);
    }

    /**
     * The gap this closes: `value` used to be validated as a loose string for
     * every key, so the API could store "abc" in `trial_days`. The reader casts
     * that to 0, and `now()->addDays(0)` issues a trial that is already over.
     */
    public function test_a_registered_key_is_validated_with_its_own_rules(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/settings/trial_days', ['value' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');

        $this->assertDatabaseMissing('settings', ['key' => 'trial_days']);
    }

    public function test_a_registered_key_rejects_a_value_outside_its_range(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/settings/license_expiry_warning_days', ['value' => '9999'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    public function test_an_invalid_support_email_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/settings/support_email', ['value' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    public function test_a_valid_value_is_stored_and_visible_to_the_reader(): void
    {
        $this->actingAsAdmin();

        // 201 when the key did not exist yet, 200 when it did - JsonResource
        // upgrades the status for a recently-created model, which is correct
        // for an upserting PUT.
        $this->putJson('/api/v1/admin/settings/trial_days', ['value' => '21'])
            ->assertSuccessful()
            ->assertJsonPath('data.key', 'trial_days');

        // The cache flush is what makes this assertion meaningful: without it
        // the reader would keep serving the pre-write value.
        $this->assertSame(21, app(PlatformSettings::class)->get('trial_days'));
    }

    /** Keys outside the registry stay free-form, as the key/value store implies. */
    public function test_an_unregistered_key_still_accepts_any_string(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/settings/some_custom_key', ['value' => 'anything at all'])
            ->assertSuccessful();

        $this->assertDatabaseHas('settings', ['key' => 'some_custom_key', 'value' => 'anything at all']);
    }
}
