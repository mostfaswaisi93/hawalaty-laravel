<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Box;
use App\Models\ContactMessage;
use App\Models\License;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Withdrawal;
use App\Services\Admin\ExpenseService;
use App\Services\Admin\PaymentService;
use App\Services\Admin\SettlementService;
use App\Services\Admin\WithdrawalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Opt-in sample data for the admin console: a cash box, partners, four months of
 * payments/costs/draws, one closed clearing run and a support inbox.
 *
 *     php artisan db:seed --class=PlatformDemoSeeder
 *
 * Goes through the SERVICES, not the models: `Box.box_balance` is maintained by
 * PaymentService (increments) and Expense/WithdrawalService (decrement, and
 * refuse to overdraw). Raw `Payment::create()` would leave every balance wrong.
 *
 * Two consequences: rows are back-dated after creation because the services
 * stamp now() while a clearing run attributes by `created_at`; and money in is
 * recorded before money out, or the overdraw guard throws.
 *
 * Idempotent - guarded on the demo box's name.
 */
class PlatformDemoSeeder extends Seeder
{
    private const BOX_NAME = 'الصندوق الرئيسي';

    /** Under 100% in total WITH any existing partners - the residual is retained. */
    private const PARTNERS = [
        ['name' => 'مصطفى سويسي', 'percentage' => 40],
        ['name' => 'شركة الأفق للتقنية', 'percentage' => 20],
    ];

    public function run(): void
    {
        if (Box::where('box_name', self::BOX_NAME)->exists()) {
            $this->command->info('Platform demo data already present - skipping.');

            return;
        }

        $licenses = License::query()->orderBy('created_at')->get();

        if ($licenses->isEmpty()) {
            $this->command->warn('No licenses yet - run DemoTenantSeeder first. Skipping platform demo.');

            return;
        }

        $box = app(\App\Services\Admin\BoxService::class)->create([
            'name' => self::BOX_NAME,
            'balance' => 0,
        ]);

        $partners = $this->seedPartners();
        $this->seedTrading($box, $licenses, $partners);
        $this->seedSettlement();
        $this->seedContactMessages();
        $this->seedSettings();

        $this->command->info('Seeded the platform console: box, partners, 4 months of payments/expenses/withdrawals, a confirmed clearing run and a support inbox.');
    }

    /** @return \Illuminate\Support\Collection<int, Partner> */
    private function seedPartners()
    {
        return collect(self::PARTNERS)->map(
            fn (array $row) => Partner::firstOrCreate(
                ['name' => $row['name']],
                ['percentage' => $row['percentage']],
            ),
        );
    }

    /**
     * Four closed months plus the current one, so the trend has a shape.
     *
     * @param  \Illuminate\Support\Collection<int, License>  $licenses
     * @param  \Illuminate\Support\Collection<int, Partner>  $partners
     */
    private function seedTrading(Box $box, $licenses, $partners): void
    {
        $payments = app(PaymentService::class);
        $expenses = app(ExpenseService::class);
        $withdrawals = app(WithdrawalService::class);

        // Costs are a SHARE of the month's income, never fixed figures: most
        // packages are priced at 0, so hard-coded costs overdrew the box.
        $costWeights = [
            ['استضافة الخوادم', 0.50],
            ['رسوم بوابة الرسائل', 0.30],
            ['اشتراك أدوات الدعم', 0.20],
        ];
        $costShareOfIncome = 0.45;
        $drawShareOfProfit = 0.40;

        $earned = 0.0;

        for ($monthsAgo = 4; $monthsAgo >= 0; $monthsAgo--) {
            $month = Carbon::today()->startOfMonth()->subMonths($monthsAgo);
            $income = 0.0;

            // --- money IN: one subscription payment per paying license ------
            foreach ($licenses as $index => $license) {
                $amount = (float) ($license->package?->price ?? 0);

                if ($amount <= 0) {
                    continue; // a free tier genuinely pays nothing
                }

                $payment = $payments->create([
                    'licenseId' => $license->id,
                    'amount' => $amount,
                    'boxId' => $box->id,
                ]);

                $this->backdate($payment, $month->copy()->addDays(2 + $index)->setTime(10, 15));
                $income += $amount;
            }

            if ($income <= 0) {
                continue; // nothing came in; spending it would overdraw
            }

            $earned += $income;

            // --- money OUT: running costs -----------------------------------
            $budget = round($income * $costShareOfIncome, 2);

            foreach ($costWeights as $i => [$description, $weight]) {
                $amount = round($budget * $weight, 2);

                if ($amount < 1) {
                    continue; // not worth a line on the finance page
                }

                // Expenses carry their own `date`, so no created_at fix-up.
                $expenses->create([
                    'boxId' => $box->id,
                    'description' => $description,
                    'amount' => $amount,
                    'date' => $month->copy()->addDays(6 + $i)->toDateString(),
                ]);
            }

            // Partner draws, skipping the open month so it still shows owed.
            if ($monthsAgo === 0) {
                continue;
            }

            foreach ($partners as $i => $partner) {
                $draw = round($income * $drawShareOfProfit * ((float) $partner->percentage / 100), 2);

                // Never overdraw - the service throws rather than go negative.
                if ($draw < 1 || bccomp((string) $box->fresh()->box_balance, (string) $draw, 2) < 0) {
                    continue;
                }

                $withdrawal = $withdrawals->create([
                    'boxId' => $box->id,
                    'amount' => $draw,
                    'partnerId' => $partner->id,
                ]);

                $this->backdate($withdrawal, $month->copy()->addDays(24 + $i)->setTime(16, 0));
            }
        }

        if ($earned <= 0) {
            $this->command->warn('Every license is on a zero-price package, so the platform recorded no income - finance and clearing will look empty.');
        }
    }

    /**
     * One confirmed run up to the end of last month, leaving the current one
     * open. Skipped rather than forced if the percentages over-allocate.
     */
    private function seedSettlement(): void
    {
        $total = (float) Partner::sum('percentage');

        if ($total > 100) {
            $this->command->warn("Partner percentages total {$total}% (>100) - skipping the demo clearing run.");

            return;
        }

        try {
            app(SettlementService::class)->create([
                'periodTo' => Carbon::today()->startOfMonth()->subDay()->toDateString(),
                'confirm' => true,
                'notes' => 'مقاصة تجريبية للأشهر السابقة.',
            ], Admin::where('email', 'admin@hawalaty.test')->value('id'));
        } catch (Throwable $e) {
            $this->command->warn('Could not create the demo clearing run: '.$e->getMessage());
        }
    }

    /** The /admin/contact-messages inbox, with two left unread for the badge. */
    private function seedContactMessages(): void
    {
        $messages = [
            ['سامي درويش', 'sami@alnoor-mart.ps', '0599111222', 'سوبرماركت النور', 'استفسار عن باقة المؤسسات', 'عندنا ثلاثة فروع ونحتاج تقارير موحّدة. هل الباقة تدعم ذلك؟', 'enterprise', true],
            ['ريم قاسم', 'reem@qasem-clinic.ps', '0598333444', 'عيادة قاسم', 'طلب عرض سعر', 'نريد نظاماً لإدارة المواعيد والحسابات. ما التكلفة الشهرية؟', 'pro', true],
            ['وليد الأحمد', 'walid@mahatta.ps', '0567555666', 'محطة المحطة للمحروقات', 'مشكلة في تسجيل الدخول', 'رمز الترخيص لا يعمل بعد تجديد الاشتراك.', null, true],
            ['نور عبد الهادي', 'noor@zaytoona.ps', '0595777888', 'مطعم الزيتونة', 'سؤال عن العمل بدون إنترنت', 'هل تُحفظ الطلبات إذا انقطع الإنترنت في الفرع؟', 'starter', false],
            ['هيثم صبري', 'haitham@sabri-store.ps', null, null, 'طلب تجربة مجانية', 'أرغب بتجربة النظام قبل الاشتراك.', null, false],
        ];

        foreach ($messages as $i => [$name, $email, $phone, $company, $subject, $body, $plan, $read]) {
            $created = Carbon::now()->subDays($i * 2 + 1)->setTime(11, 30);

            // Matched on sender + subject: the box guard replays this method
            // after a clean-out, and plain create() doubled the inbox.
            $message = ContactMessage::firstOrCreate(
                ['email' => $email, 'subject' => $subject],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'company' => $company,
                    'message' => $body,
                    'plan' => $plan,
                    'ip_address' => '192.0.2.'.(10 + $i),   // TEST-NET-1, never a real host
                    'locale' => 'ar',
                ],
            );

            if (! $message->wasRecentlyCreated) {
                continue;
            }

            DB::table('contact_messages')->where('id', $message->id)->update([
                'created_at' => $created,
                'updated_at' => $created,
                'read_at' => $read ? $created->copy()->addHours(3) : null,
            ]);
        }
    }

    /** Only the keys an operator would brand; the rest keep registry defaults. */
    private function seedSettings(): void
    {
        foreach ([
            'site_name' => 'حوالاتي',
            'support_email' => 'support@hawalaty.test',
            'support_phone' => '+970599123456',
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /** Rewrites `created_at` past the service's now() stamp, bypassing the model. */
    private function backdate(Payment|Withdrawal $model, Carbon $when): void
    {
        DB::table($model->getTable())->where('id', $model->id)->update([
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }
}
