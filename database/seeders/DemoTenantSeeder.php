<?php

namespace Database\Seeders;

use App\Enums\AccountKind;
use App\Enums\ChangeMethod;
use App\Enums\PaymentMethod;
use App\Enums\RoleKey;
use App\Enums\SalaryPeriod;
use App\Enums\TransferStatus;
use App\Models\License;
use App\Models\Tenant\Account;
use App\Models\Tenant\AccountType;
use App\Models\Tenant\BankName;
use App\Models\Tenant\CashBox;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\ConstantListItem;
use App\Models\Tenant\Currency;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Expense;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\SalaryPayment;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\TenantSetting;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\TransferLineItem;
use App\Models\Tenant\WalletName;
use App\Models\Tenant\WalletType;
use App\Services\Tenant\SalaryService;
use App\Services\TenantConnectionResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoTenantSeeder extends Seeder
{
    private const OWNER_PASSWORD = TenantSeeder::OWNER_PASSWORD;

    private const CUSTOMERS = [
        ['أحمد أبو ياسين', '0599100001'],
        ['محمد العرّاج', '0599100002'],
        ['سميرة خليل', '0599100003'],
        ['يوسف النجّار', '0599100004'],
        ['هبة عيّاش', '0599100005'],
        ['خالد شاهين', '0599100006'],
        ['رنا الطويل', '0599100007'],
        ['عبدالله زيدان', '0599100008'],
        ['لينا حدّاد', '0599100009'],
        ['مازن قنديل', '0599100010'],
        ['دعاء سلامة', '0599100011'],
        ['طارق البيطار', '0599100012'],
    ];

    public function run(): void
    {
        $license = License::where('customer_name', TenantSeeder::BUSINESS_NAME)->first();

        if (! $license) {
            $this->command->warn('Tenant «'.TenantSeeder::BUSINESS_NAME.'» does not exist yet - run `php artisan db:seed` first.');

            return;
        }

        $this->command->info('Filling «'.TenantSeeder::BUSINESS_NAME."» (license {$license->license_code}) with sample data.");

        TenantConnectionResolver::resolve($license->license_code);
        app()->instance('currentLicense', $license);

        $filled = [];

        DB::connection('tenant')->transaction(function () use (&$filled) {
            $accounts = Account::count() > 0 ? Account::get() : tap($this->seedAccounts(), fn () => $filled[] = 'accounts');
            $items = MenuItem::count() > 0 ? MenuItem::get() : tap($this->seedMenu(), fn () => $filled[] = 'menu');
            $members = $this->seedTeam($filled);

            $this->tally($filled, 'customers', $this->seedCustomers());
            $this->tally($filled, 'constants', $this->seedConstants());
            $this->tally($filled, 'cash boxes', $this->seedCashBoxes());

            if (Transfer::count() === 0) {
                $this->seedTransfers($accounts, $items);
                $filled[] = 'transfers';
            }

            if (Expense::count() === 0) {
                $this->seedExpenses($accounts);
                $filled[] = 'expenses';
            }

            if (SalaryPayment::count() === 0) {
                $this->seedSalaries($members, $accounts);
                $filled[] = 'payroll';
            }

            $this->seedTenantSettings();

        });

        $this->command->info($filled === []
            ? 'Sample data already complete - nothing to add.'
            : 'Seeded '.implode(', ', $filled).'.');
    }

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

    private function seedTeam(array &$filled): Collection
    {
        $manager = Role::where('key', RoleKey::Manager->value)->first();
        $staff = Role::where('key', RoleKey::Staff->value)->first();

        $members = [
            ['name' => 'سميرة خليل', 'email' => 'manager@demo.test', 'role_id' => $manager?->id, 'salary_amount' => 2800, 'salary_period' => SalaryPeriod::Monthly->value],
            ['name' => 'يوسف النجّار', 'email' => 'staff1@demo.test', 'role_id' => $staff?->id, 'salary_amount' => 1900, 'salary_period' => SalaryPeriod::Monthly->value],
            ['name' => 'هبة عيّاش', 'email' => 'staff2@demo.test', 'role_id' => $staff?->id, 'salary_amount' => 120, 'salary_period' => SalaryPeriod::Daily->value],
        ];

        $touched = false;
        $result = collect();

        foreach ($members as $member) {
            if (! $member['role_id']) {
                continue;
            }

            $existing = TeamMember::where('email', $member['email'])->first();

            if (! $existing) {
                $result->push(TeamMember::create($member + ['password' => self::OWNER_PASSWORD, 'active' => true]));
                $touched = true;

                continue;
            }

            if ($existing->salary_amount === null) {
                $existing->forceFill([
                    'salary_amount' => $member['salary_amount'],
                    'salary_period' => $member['salary_period'],
                ])->save();
                $touched = true;
            }

            $result->push($existing);
        }

        if ($touched) {
            $filled[] = 'team';
        }

        return $result;
    }

    private function seedCustomers(): int
    {
        $created = 0;

        foreach (self::CUSTOMERS as [$name, $phone]) {
            $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $name]);
            $created += $customer->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    private function tally(array &$filled, string $label, int $created): void
    {
        if ($created > 0) {
            $filled[] = "{$label} ({$created})";
        }
    }

    private function seedConstants(): int
    {
        $lists = [
            'طرق التوصيل' => ['استلام من المطعم', 'توصيل داخل المدينة', 'توصيل خارج المدينة', 'تطبيق توصيل'],
            'ملاحظات الطلب' => ['بدون بصل', 'حار إضافي', 'بدون مايونيز', 'تغليف هدية', 'مستعجل'],
            'أسباب الإلغاء' => ['العميل ألغى', 'الصنف غير متوفر', 'خطأ في الطلب', 'تأخر التوصيل'],
        ];

        $created = 0;

        foreach ($lists as $title => $values) {
            $list = ConstantList::firstOrCreate(
                ['title' => $title],
                ['key' => 'custom-'.Str::lower(Str::random(10))],
            );

            $created += $list->wasRecentlyCreated ? 1 : 0;

            foreach ($values as $position => $value) {
                $item = ConstantListItem::firstOrCreate(
                    ['constant_list_id' => $list->id, 'value' => $value],
                    ['position' => $position + 1],
                );

                $created += $item->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $created;
    }

    private function seedCashBoxes(): int
    {
        $created = 0;

        foreach (['الصندوق الرئيسي', 'صندوق الكاشير', 'صندوق التوصيل'] as $name) {
            $box = CashBox::firstOrCreate(['name' => $name], ['active' => true]);
            $created += $box->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    private function seedSalaries(Collection $members, Collection $accounts): void
    {
        if ($members->isEmpty() || $accounts->isEmpty()) {
            return;
        }

        $service = app(SalaryService::class);
        $accountId = $accounts->first()->id;

        foreach ($members as $member) {
            if (! $member->salary_amount) {
                continue;
            }

            for ($monthsAgo = 3; $monthsAgo >= 1; $monthsAgo--) {
                $paidFor = Carbon::today()->startOfMonth()->subMonths($monthsAgo);

                $service->pay($member, [
                    'amount' => (float) $member->salary_amount,
                    'accountId' => $accountId,
                    'paidFor' => $paidFor->toDateString(),
                    'period' => $member->salary_period?->value,
                ]);
            }
        }
    }

    private function seedTenantSettings(): void
    {
        foreach ([
            'business_phone' => '+970599123456',
            'business_address' => 'شارع عمر المختار، غزة',
            'receipt_footer' => 'شكراً لزيارتكم - مطعم البركة',
        ] as $key => $value) {
            TenantSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function seedTransfers(Collection $accounts, Collection $items): void
    {
        $dailyCounts = [6 => 4, 5 => 6, 4 => 3, 3 => 10, 2 => 5, 1 => 8, 0 => 2];

        foreach ($dailyCounts as $offset => $count) {
            $anchor = $offset === 0
                ? Carbon::now()->subMinutes(45)
                : Carbon::today()->subDays($offset)->setTime(20, 0);

            for ($i = 0; $i < $count; $i++) {
                $this->makeTransfer($anchor->copy()->subMinutes($i * 9 + random_int(0, 6)), $accounts, $items);
            }
        }

        for ($offset = 8; $offset <= 14; $offset++) {
            $anchor = Carbon::today()->subDays($offset)->setTime(19, 30);

            for ($i = 0; $i < 2; $i++) {
                $this->makeTransfer($anchor->copy()->subMinutes($i * 20), $accounts, $items);
            }
        }

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

    private function makeTransfer(Carbon $createdAt, Collection $accounts, Collection $items, ?TransferStatus $status = null, ?PaymentMethod $payment = null): void
    {
        $customers = array_column(self::CUSTOMERS, 0);

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
        $transfer->timestamps = false;

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
}
