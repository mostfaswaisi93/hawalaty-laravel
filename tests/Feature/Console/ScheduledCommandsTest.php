<?php

namespace Tests\Feature\Console;

use App\Enums\BackupStatus;
use App\Enums\BackupTrigger;
use App\Enums\LicenseStatus;
use App\Models\ActivityLog;
use App\Models\Backup;
use App\Models\License;
use App\Models\Package;
use App\Services\Admin\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The only code in this system that runs with nobody watching.
 *
 * Everything else fails in front of a user who can report it. These two run at
 * midnight and 02:00, and until now neither had a single test - nor did
 * anything check they were still REGISTERED. That second half is the sharper
 * edge: Schedule::command() takes a string, so a typo in routes/console.php
 * does not fail at boot. It fails silently, every night, and the first symptom
 * is a customer whose licence never expired or a backup that was never taken.
 */
class ScheduledCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function license(LicenseStatus $status, string $expiredAt, string $code = 'LIC00001'): License
    {
        $package = Package::firstOrCreate(
            ['name' => 'Starter'],
            ['price' => 30, 'device_limit' => 2, 'features' => []],
        );

        return License::create([
            'license_code' => $code,
            'customer_name' => 'مطعم '.$code,
            'package_id' => $package->id,
            'status' => $status,
            'expired_at' => $expiredAt,
            'customer_balance' => 0,
        ]);
    }

    // --- the schedule itself ----------------------------------------------

    /**
     * Maps each scheduled event to the artisan command it will actually run.
     *
     * @return array<string, string> command name => cron expression
     */
    private function scheduled(): array
    {
        $events = app(Schedule::class)->events();
        $commands = [];

        foreach ($events as $event) {
            // Event::$command is the full shell string - `"/path/php" "artisan"
            // licenses:expire-overdue` - so the name has to be dug back out.
            if (preg_match('/artisan[\'"]?\s+[\'"]?([\w:.-]+)/', (string) $event->command, $matches)) {
                $commands[$matches[1]] = $event->expression;
            }
        }

        // Without this the two tests below would pass on an empty array, which
        // is exactly the failure they exist to catch.
        $this->assertCount(
            count($events),
            $commands,
            'Could not read a command name out of every scheduled event; the parsing above is wrong.',
        );

        return $commands;
    }

    public function test_the_licence_expiry_sweep_is_scheduled_daily(): void
    {
        $this->assertSame('0 0 * * *', $this->scheduled()['licenses:expire-overdue'] ?? null);
    }

    public function test_the_backup_is_scheduled_at_two_in_the_morning(): void
    {
        $this->assertSame('0 2 * * *', $this->scheduled()['backups:scheduled'] ?? null);
    }

    /**
     * The one that a typo actually breaks. A scheduled name that matches no
     * registered command is accepted at boot and only fails when it fires.
     */
    public function test_every_scheduled_name_is_a_command_that_exists(): void
    {
        $registered = array_keys(Artisan::all());

        foreach (array_keys($this->scheduled()) as $name) {
            $this->assertContains($name, $registered, "Scheduled `{$name}` is not a registered command.");
        }
    }

    // --- licenses:expire-overdue ------------------------------------------

    public function test_an_overdue_licence_is_expired(): void
    {
        $license = $this->license(LicenseStatus::Active, now()->subDay()->toDateString());

        $this->artisan('licenses:expire-overdue')->assertSuccessful();

        $this->assertSame(LicenseStatus::Expired, $license->refresh()->status);
    }

    public function test_an_overdue_trial_is_expired_too(): void
    {
        $license = $this->license(LicenseStatus::Trial, now()->subMonth()->toDateString());

        $this->artisan('licenses:expire-overdue')->assertSuccessful();

        $this->assertSame(LicenseStatus::Expired, $license->refresh()->status);
    }

    /**
     * The boundary, and the one worth stating out loud: a licence is valid
     * THROUGH its expiry date. Expiring on the morning of the date the customer
     * paid for takes a day off every subscription sold.
     */
    public function test_a_licence_expiring_today_is_left_alone(): void
    {
        $license = $this->license(LicenseStatus::Active, now()->toDateString());

        $this->artisan('licenses:expire-overdue')->assertSuccessful();

        $this->assertSame(LicenseStatus::Active, $license->refresh()->status);
    }

    public function test_a_future_licence_is_left_alone(): void
    {
        $license = $this->license(LicenseStatus::Active, now()->addMonth()->toDateString());

        $this->artisan('licenses:expire-overdue')->assertSuccessful();

        $this->assertSame(LicenseStatus::Active, $license->refresh()->status);
    }

    /**
     * Suspended is an administrative decision, not a date. Sweeping it into
     * `expired` would quietly rewrite why the account was stopped.
     */
    public function test_a_suspended_licence_is_not_swept_into_expired(): void
    {
        $license = $this->license(LicenseStatus::Suspended, now()->subYear()->toDateString());

        $this->artisan('licenses:expire-overdue')->assertSuccessful();

        $this->assertSame(LicenseStatus::Suspended, $license->refresh()->status);
    }

    /** Every expiry has to leave a trace - this is a billing state change. */
    public function test_each_expiry_is_written_to_the_activity_log(): void
    {
        $this->license(LicenseStatus::Active, now()->subDay()->toDateString(), 'LOGGED01');

        $this->artisan('licenses:expire-overdue')->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'expired',
            'entity_type' => 'license',
            'entity_label' => 'مطعم LOGGED01',
            'admin_id' => null,
        ]);
    }

    /**
     * The command runs every night on the same table, so it has to be
     * idempotent. A second pass must not re-log licences it already expired -
     * otherwise the activity log fills with one duplicate per licence per day.
     */
    public function test_running_it_twice_expires_nothing_a_second_time(): void
    {
        $this->license(LicenseStatus::Active, now()->subDay()->toDateString());

        $this->artisan('licenses:expire-overdue')->assertSuccessful();
        $this->artisan('licenses:expire-overdue')->assertSuccessful();

        $this->assertSame(1, ActivityLog::where('action', 'expired')->count());
    }

    public function test_it_succeeds_when_there_is_nothing_to_expire(): void
    {
        $this->artisan('licenses:expire-overdue')->assertSuccessful();

        $this->assertSame(0, ActivityLog::count());
    }

    // --- backups:scheduled ------------------------------------------------

    private function fakeBackup(BackupStatus $status): Backup
    {
        return Backup::create([
            'license_id' => null,
            'trigger' => BackupTrigger::Scheduled,
            'actor_name' => '',
            'status' => $status,
        ]);
    }

    public function test_the_backup_command_runs_a_scheduled_backup(): void
    {
        $backup = $this->fakeBackup(BackupStatus::Completed);

        // Mocked rather than executed: the real service shells out to
        // mysqldump, which is not this test's subject and is not present on
        // every machine that runs the suite.
        $this->mock(BackupService::class)
            ->shouldReceive('runScheduled')
            ->once()
            ->withNoArgs()
            ->andReturn($backup);

        $this->artisan('backups:scheduled')->assertSuccessful();
    }

    /**
     * A failed backup has to leave a non-zero exit code behind. Cron and every
     * supervisor above it decide whether to alert on exactly that - a command
     * that reports success after failing to back anything up is worse than no
     * backup at all, because it is silent.
     */
    public function test_a_failed_backup_exits_non_zero(): void
    {
        $backup = $this->fakeBackup(BackupStatus::Failed);

        $this->mock(BackupService::class)
            ->shouldReceive('runScheduled')
            ->once()
            ->andReturn($backup);

        $this->artisan('backups:scheduled')->assertFailed();
    }
}
