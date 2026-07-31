<?php

namespace Database\Seeders;

use App\Enums\AccountKind;
use App\Enums\ChangeMethod;
use App\Enums\PaymentMethod;
use App\Enums\RoleKey;
use App\Enums\TransferStatus;
use App\Models\License;
use App\Models\Package;
use App\Models\Tenant\Account;
use App\Models\Tenant\AccountType;
use App\Models\Tenant\BankName;
use App\Models\Tenant\Currency;
use App\Models\Tenant\WalletName;
use App\Models\Tenant\WalletType;
use App\Models\Tenant\Expense;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\TransferLineItem;
use App\Services\TenantConnectionResolver;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Provisions ONE fully-working demo tenant (a Palestinian restaurant) and
 * fills it with realistic Arabic data so the dashboard's revenue tile,
 * weekly-activity chart and recent-orders table all look real.
 *
 * It reuses App\Services\TenantProvisioner - the exact same service the
 * signup flow uses - so it creates a real License + physical MySQL database
 * + user, runs the tenant migrations, and seeds the baseline
 * permissions/roles/owner, then adds the demo domain rows on top.
 *
 * Idempotent: the whole thing is guarded by an existence check on the demo
 * license's customer_name (so re-runs never leak orphan databases/users),
 * and the domain data is only seeded once (guarded by Account::count()). The
 * generated license code + login credentials are printed at the end.
 */
class DemoTenantSeeder extends Seeder
{
    private const BUSINESS_NAME = 'مطعم البركة (تجريبي)';

    private const OWNER_NAME = 'أحمد البركة';

    private const OWNER_EMAIL = 'owner@demo.test';

    private const OWNER_PASSWORD = 'password';

    /** Anchored so the "last 7 days" always includes the seeding day. */
    public function run(): void
    {
        $license = License::where('customer_name', self::BUSINESS_NAME)->first();

        if (! $license) {
            $license = $this->provisionTenant();

            if (! $license) {
                return; // provisioning failed - a hint was already printed
            }
        } else {
            $this->command->info('Demo tenant «'.self::BUSINESS_NAME."» already exists (license {$license->license_code}).");
        }

        TenantConnectionResolver::resolve($license->license_code);
        app()->instance('currentLicense', $license);

        if (Account::count() > 0) {
            $this->command->info('Demo tenant domain data already present - skipping.');
        } else {
            DB::connection('tenant')->transaction(function () {
                $accounts = $this->seedAccounts();
                $items = $this->seedMenu();
                $this->seedTeam();
                $this->seedTransfers($accounts, $items);
                $this->seedExpenses($accounts);
            });

            $this->command->info('Seeded accounts, menu, team, ~55 transfers and expenses for the demo tenant.');
        }

        $this->printCredentials($license->license_code);
    }

    private function provisionTenant(): ?License
    {
        $package = Package::where('name', 'Pro')->first() ?? Package::orderBy('price')->first();

        if (! $package) {
            $this->command->warn('No package found - run PackageSeeder first. Skipping demo tenant.');

            return null;
        }

        try {
            /** @var TenantProvisioner $provisioner */
            $provisioner = app(TenantProvisioner::class);

            $license = $provisioner->provision([
                'businessName' => self::BUSINESS_NAME,
                'ownerName' => self::OWNER_NAME,
                'ownerEmail' => self::OWNER_EMAIL,
                'phone' => '0599123456',
                'password' => self::OWNER_PASSWORD,
                'packageId' => $package->id,
            ]);

            $this->command->info('Provisioned demo tenant «'.self::BUSINESS_NAME."» (license {$license->license_code}).");

            return $license;
        } catch (Throwable $e) {
            $this->command->error('Failed to provision the demo tenant: '.$e->getMessage());
            $this->command->warn('The DB user in your .env needs CREATE DATABASE / CREATE USER / GRANT privileges (Laragon root has them).');

            return null;
        }
    }

    /**
     * The reference catalogs plus one account per institution. `whatsapp_phone`
     * is the number new-order notifications are sent to, stored in E.164
     * exactly as the account form saves it.
     *
     * @return Collection<int, Account>
     */
    private function seedAccounts(): Collection
    {
        $currency = Currency::create(['name' => 'شيكل', 'symbol' => '₪', 'active' => true]);
        $accountType = AccountType::create(['name' => 'جاري', 'active' => true]);
        $walletType = WalletType::create(['name' => 'تجارية', 'active' => true]);

        $institutions = [
            ['kind' => AccountKind::Bank, 'name' => 'بنك فلسطين', 'holder' => 'مطعم الأصيل', 'phone' => '+972599000001'],
            ['kind' => AccountKind::Bank, 'name' => 'البنك الإسلامي الفلسطيني', 'holder' => 'مطعم الأصيل', 'phone' => '+972599000002'],
            ['kind' => AccountKind::Bank, 'name' => 'بنك القدس', 'holder' => 'مطعم الأصيل', 'phone' => '+972599000003'],
            ['kind' => AccountKind::Bank, 'name' => 'البنك الوطني', 'holder' => 'مطعم الأصيل', 'phone' => '+972599000004'],
            ['kind' => AccountKind::Wallet, 'name' => 'جوال باي', 'holder' => 'مطعم الأصيل', 'phone' => '+972599000005'],
            ['kind' => AccountKind::Wallet, 'name' => 'PalPay', 'holder' => 'مطعم الأصيل', 'phone' => '+972599000006'],
            ['kind' => AccountKind::Wallet, 'name' => 'ريفلكت', 'holder' => 'مطعم الأصيل', 'phone' => '+972595817016'],
        ];

        return collect($institutions)->map(function (array $row) use ($currency, $accountType, $walletType) {
            $isBank = $row['kind'] === AccountKind::Bank;

            $institution = $isBank
                ? BankName::create(['name' => $row['name'], 'active' => true])
                : WalletName::create(['name' => $row['name'], 'active' => true]);

            // An account may only use a currency its institution supports.
            $institution->currencies()->attach($currency->id);

            return Account::create([
                'kind' => $row['kind']->value,
                'bank_name_id' => $isBank ? $institution->id : null,
                'account_type_id' => $isBank ? $accountType->id : null,
                'wallet_name_id' => $isBank ? null : $institution->id,
                'wallet_type_id' => $isBank ? null : $walletType->id,
                'wallet_phone' => $isBank ? null : $row['phone'],
                'currency_id' => $currency->id,
                'holder_name' => $row['holder'],
                'whatsapp_phone' => $row['phone'],
                'balance' => 0,
                'active' => true,
            ]);
        });
    }

    /** @return Collection<int, MenuItem> every active menu item, for building line items */
    private function seedMenu(): Collection
    {
        $catalog = [
            ['name' => 'المشاوي', 'items' => [['شاورما لحمة', 25], ['شاورما دجاج', 20], ['شيش طاووق', 35], ['كباب', 40], ['مشاوي مشكلة', 55]]],
            ['name' => 'الوجبات', 'items' => [['مقلوبة', 30], ['مسخّن', 35], ['منسف', 45], ['أوزي', 40]]],
            ['name' => 'المعجنات', 'items' => [['مناقيش زعتر', 5], ['فطاير سبانخ', 6], ['صفيحة لحمة', 7]]],
            ['name' => 'المشروبات', 'items' => [['عصير ليمون نعناع', 8], ['كوكاكولا', 5], ['ماء', 2], ['شاي', 3]]],
            ['name' => 'الحلويات', 'items' => [['كنافة نابلسية', 15], ['بقلاوة', 12]]],
        ];

        $allItems = collect();

        foreach ($catalog as $index => $category) {
            $model = MenuCategory::create(['name' => $category['name'], 'position' => $index + 1]);

            foreach ($category['items'] as [$name, $price]) {
                $allItems->push(MenuItem::create([
                    'menu_category_id' => $model->id,
                    'name' => $name,
                    'price' => $price,
                    'active' => true,
                ]));
            }
        }

        return $allItems;
    }

    private function seedTeam(): void
    {
        $manager = Role::where('key', RoleKey::Manager->value)->first();
        $staff = Role::where('key', RoleKey::Staff->value)->first();

        $members = [
            ['name' => 'سميرة خليل', 'email' => 'manager@demo.test', 'role_id' => $manager?->id],
            ['name' => 'يوسف النجّار', 'email' => 'staff1@demo.test', 'role_id' => $staff?->id],
            ['name' => 'هبة عيّاش', 'email' => 'staff2@demo.test', 'role_id' => $staff?->id],
        ];

        foreach ($members as $member) {
            if (! $member['role_id']) {
                continue;
            }

            TeamMember::create($member + ['password' => self::OWNER_PASSWORD, 'active' => true]);
        }
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, MenuItem>  $items
     */
    private function seedTransfers(Collection $accounts, Collection $items): void
    {
        // Last 7 days, each with a different non-zero count and a clear peak
        // four days ago (10) so the weekly bar chart reads as real activity.
        $dailyCounts = [6 => 4, 5 => 6, 4 => 3, 3 => 10, 2 => 5, 1 => 8, 0 => 2];

        foreach ($dailyCounts as $offset => $count) {
            $anchor = $offset === 0
                ? Carbon::now()->subMinutes(45)
                : Carbon::today()->subDays($offset)->setTime(20, 0);

            for ($i = 0; $i < $count; $i++) {
                $this->makeTransfer($anchor->copy()->subMinutes($i * 9 + random_int(0, 6)), $accounts, $items);
            }
        }

        // Older orders (8..14 days ago) to inflate totals and fill /history.
        for ($offset = 8; $offset <= 14; $offset++) {
            $anchor = Carbon::today()->subDays($offset)->setTime(19, 30);

            for ($i = 0; $i < 2; $i++) {
                $this->makeTransfer($anchor->copy()->subMinutes($i * 20), $accounts, $items);
            }
        }

        // The 5 NEWEST orders are exactly what the dashboard's recent-orders
        // table shows, so make them deliberately varied (mixed statuses +
        // cash/bank) and strictly the latest by created_at.
        $newest = [
            [TransferStatus::Completed, PaymentMethod::Bank],
            [TransferStatus::Completed, PaymentMethod::Cash],
            [TransferStatus::Pending, PaymentMethod::Bank],
            [TransferStatus::Failed, PaymentMethod::Cash],
            [TransferStatus::Completed, PaymentMethod::Cash],
        ];

        foreach ($newest as $i => [$status, $payment]) {
            $this->makeTransfer(Carbon::now()->subMinutes($i * 2), $accounts, $items, $status, $payment);
        }
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, MenuItem>  $items
     */
    private function makeTransfer(Carbon $createdAt, Collection $accounts, Collection $items, ?TransferStatus $status = null, ?PaymentMethod $payment = null): void
    {
        $customers = ['أحمد أبو ياسين', 'محمد العرّاج', 'سميرة خليل', 'يوسف النجّار', 'هبة عيّاش', 'خالد شاهين', 'رنا الطويل', 'عبدالله زيدان', 'لينا حدّاد', 'مازن قنديل', 'دعاء سلامة', 'طارق البيطار'];

        $chosen = $items->random(random_int(1, min(4, $items->count())));
        $lines = $chosen->map(fn (MenuItem $item) => ['item' => $item, 'qty' => random_int(1, 3)]);

        $amount = $lines->sum(fn (array $line) => $line['qty'] * (float) $line['item']->price);
        $itemName = $lines->map(fn (array $line) => "{$line['item']->name} ×{$line['qty']}")->implode('، ');

        $status ??= $this->weightedStatus();
        $payment ??= random_int(1, 100) <= 55 ? PaymentMethod::Cash : PaymentMethod::Bank;

        $attributes = [
            'customer' => $customers[array_rand($customers)],
            'status' => $status,
            'amount' => $amount,
            'item_name' => $itemName,
            'payment_method' => $payment,
            'has_change' => false,
        ];

        if ($payment === PaymentMethod::Cash) {
            if (random_int(0, 1) === 1) {
                $attributes['has_change'] = true;
                $attributes['change_method'] = ChangeMethod::Cash;
                $attributes['change_amount'] = random_int(1, 20);
            }
        } else {
            $attributes['account_id'] = $accounts->random()->id;
            $attributes['sender_name'] = $customers[array_rand($customers)];
            $attributes['sender_phone'] = '059'.random_int(1000000, 9999999);
            $attributes['transferred_amount'] = $amount;
        }

        $transfer = new Transfer($attributes);
        $transfer->created_at = $createdAt;
        $transfer->updated_at = $createdAt;
        $transfer->timestamps = false; // keep the back-dated created_at instead of now()
        $transfer->save();

        foreach ($lines as $line) {
            TransferLineItem::create([
                'transfer_id' => $transfer->id,
                'menu_item_id' => $line['item']->id,
                'name' => $line['item']->name,
                'quantity' => $line['qty'],
                'price' => $line['item']->price,
            ]);
        }
    }

    private function weightedStatus(): TransferStatus
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 75 => TransferStatus::Completed,
            $roll <= 95 => TransferStatus::Pending,
            default => TransferStatus::Failed,
        };
    }

    /** @param Collection<int, Account> $accounts */
    private function seedExpenses(Collection $accounts): void
    {
        $expenses = [
            ['غاز للمطبخ', 150], ['رواتب عمال', 2000], ['خضار وفواكه', 400], ['لحوم ودجاج', 1200],
            ['فاتورة كهرباء', 320], ['صيانة أفران', 180], ['أكياس وتغليف', 90], ['مياه غازية', 250],
        ];

        $accountIds = $accounts->pluck('id')->all();

        foreach ($expenses as [$description, $amount]) {
            Expense::create([
                'description' => $description,
                'amount' => $amount,
                'account_id' => $accountIds[array_rand($accountIds)],
                'date' => Carbon::today()->subDays(random_int(1, 13)),
            ]);
        }
    }

    private function printCredentials(string $licenseCode): void
    {
        $this->command->newLine();
        $this->command->getOutput()->writeln('  <fg=black;bg=green> بيانات دخول المستأجر التجريبي / DEMO TENANT LOGIN </>');
        $this->command->line("  رمز الترخيص / License code : <info>{$licenseCode}</info>");
        $this->command->line('  البريد / Email             : <info>'.self::OWNER_EMAIL.'</info>');
        $this->command->line('  كلمة المرور / Password     : <info>'.self::OWNER_PASSWORD.'</info>');
        $this->command->newLine();
    }
}
