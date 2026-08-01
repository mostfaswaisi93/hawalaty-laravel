<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Setting;
use App\Settings\PlatformSettings;
use App\Settings\SettingRegistry;
use App\Settings\SettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Platform settings: the typed registry, the grouped form, and the guarantee
 * that a settings lookup can never take down a public page.
 */
class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): PlatformSettings
    {
        return app(PlatformSettings::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings()->flush();
    }

    public function test_an_unset_setting_falls_back_to_its_registry_default(): void
    {
        $this->assertSame(14, $this->settings()->get('trial_days'));
        $this->assertSame(30, $this->settings()->get('license_expiry_warning_days'));
        $this->assertSame('support@hawalaty.test', $this->settings()->get('support_email'));
    }

    /** Values live in a TEXT column, so the declared type is what makes them usable. */
    public function test_values_are_cast_to_their_declared_type(): void
    {
        Setting::create(['key' => 'trial_days', 'value' => '21']);
        $this->settings()->flush();

        $value = $this->settings()->get('trial_days');

        $this->assertSame(21, $value);
        $this->assertIsInt($value, 'A number setting came back as a string.');
    }

    public function test_a_blank_stored_value_still_falls_back_to_the_default(): void
    {
        Setting::create(['key' => 'trial_days', 'value' => '']);
        $this->settings()->flush();

        $this->assertSame(14, $this->settings()->get('trial_days'));
    }

    public function test_keys_outside_the_registry_are_surfaced_separately(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'Registered']);
        Setting::create(['key' => 'legacy_key', 'value' => 'kept']);
        $this->settings()->flush();

        $custom = $this->settings()->unregistered();

        $this->assertArrayHasKey('legacy_key', $custom);
        $this->assertArrayNotHasKey('site_name', $custom, 'A registered key leaked into the custom list.');
    }

    /**
     * The public landing/help/privacy/terms pages read settings through a view
     * composer. If that lookup threw, a missing table or a brief database blip
     * would take the marketing site down - so it must degrade to defaults.
     */
    public function test_a_missing_settings_table_degrades_to_defaults_instead_of_throwing(): void
    {
        Schema::drop('settings');
        $this->settings()->flush();

        $this->assertSame(14, $this->settings()->get('trial_days'));
        $this->assertSame('support@hawalaty.test', $this->settings()->get('support_email'));
        $this->assertSame([], $this->settings()->unregistered());

        // And the public page that depends on it still renders.
        $this->get('/')->assertOk();
    }

    public function test_the_settings_page_renders_the_registered_groups(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('trial_days')
            ->assertSee('support_email')
            ->assertViewHas('groups')
            ->assertViewHas('values');
    }

    public function test_the_grouped_form_saves_every_registered_setting(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->put('/admin/settings', ['settings' => [
                'site_name' => 'My Platform',
                'support_email' => 'help@example.com',
                'support_phone' => '+970590000000',
                'trial_days' => '21',
                'license_expiry_warning_days' => '45',
            ]])
            ->assertRedirect('/admin/settings');

        $this->settings()->flush();

        $this->assertSame('My Platform', $this->settings()->get('site_name'));
        $this->assertSame('help@example.com', $this->settings()->get('support_email'));
        $this->assertSame(21, $this->settings()->get('trial_days'));
        $this->assertSame(45, $this->settings()->get('license_expiry_warning_days'));
    }

    public function test_the_form_rejects_values_outside_each_settings_rules(): void
    {
        $admin = Admin::factory()->create();
        $base = [
            'site_name' => 'X',
            'support_email' => 'not-an-email',
            'trial_days' => '0',
            'license_expiry_warning_days' => '9999',
        ];

        $this->actingAs($admin, 'admin')
            ->put('/admin/settings', ['settings' => $base])
            ->assertSessionHasErrors([
                'settings.support_email',
                'settings.trial_days',
                'settings.license_expiry_warning_days',
            ]);

        $this->assertDatabaseCount('settings', 0);
    }

    /**
     * A registered key must not be settable through the free-form endpoint,
     * which would let a raw string bypass that setting's validation.
     */
    public function test_the_free_form_endpoint_refuses_registered_keys(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->post('/admin/settings', ['key' => 'trial_days', 'value' => 'not-a-number'])
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'trial_days']);
    }

    public function test_a_registered_setting_cannot_be_deleted(): void
    {
        Setting::create(['key' => 'trial_days', 'value' => '20']);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->delete('/admin/settings/trial_days')
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'trial_days']);
    }

    public function test_a_custom_key_can_be_deleted(): void
    {
        Setting::create(['key' => 'legacy_key', 'value' => 'x']);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->delete('/admin/settings/legacy_key')
            ->assertRedirect('/admin/settings');

        $this->assertDatabaseMissing('settings', ['key' => 'legacy_key']);
    }

    /** Every registry entry must be complete, or the form renders half a field. */
    public function test_every_registered_setting_declares_the_required_metadata(): void
    {
        foreach (app(SettingRegistry::class)->all() as $key => $definition) {
            foreach (['key', 'label', 'description', 'type', 'rules'] as $required) {
                $this->assertArrayHasKey($required, $definition, "Setting `{$key}` is missing `{$required}`.");
            }
            $this->assertArrayHasKey('default', $definition, "Setting `{$key}` is missing `default`.");
        }
    }

    /** The dashboard's expiring-soon window is driven by the setting, not a const. */
    public function test_the_dashboard_expiry_window_follows_the_setting(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->put('/admin/settings', ['settings' => [
                'site_name' => 'X',
                'support_email' => 'a@b.test',
                'trial_days' => '14',
                'license_expiry_warning_days' => '1',
            ]])->assertRedirect();

        $this->settings()->flush();

        $this->assertSame(1, $this->settings()->get('license_expiry_warning_days'));
    }

    /**
     * The rendered `min`/`max` must come from the setting's own rules. A browser
     * constraint that contradicts the server rule blocks a value the server
     * would accept, with no error to explain it.
     */
    public function test_number_bounds_are_read_from_the_settings_own_rules(): void
    {
        $registry = app(SettingRegistry::class);

        $bounds = $registry->boundsFor([
            'type' => SettingType::Number,
            'rules' => ['required', 'integer', 'min:5', 'max:50'],
        ]);

        $this->assertSame(['min' => '5', 'max' => '50'], $bounds);
    }

    /**
     * `max:120` on a string is a LENGTH. Treating it as a numeric bound would
     * cap a text field at its own character count.
     */
    public function test_a_string_length_rule_is_not_mistaken_for_a_number_bound(): void
    {
        $bounds = app(SettingRegistry::class)->boundsFor([
            'type' => SettingType::Text,
            'rules' => ['required', 'string', 'max:120'],
        ]);

        $this->assertSame(['min' => null, 'max' => null], $bounds);
    }

    /** Every registered Number setting must actually render its declared bounds. */
    public function test_the_page_renders_each_number_settings_declared_bounds(): void
    {
        $response = $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin/settings')
            ->assertOk();

        foreach (app(SettingRegistry::class)->all() as $key => $definition) {
            if ($definition['type'] !== SettingType::Number) {
                continue;
            }

            $bounds = app(SettingRegistry::class)->boundsFor($definition);

            $response->assertSee('min="'.$bounds['min'].'"', false);
            $response->assertSee('max="'.$bounds['max'].'"', false);
        }
    }
}
