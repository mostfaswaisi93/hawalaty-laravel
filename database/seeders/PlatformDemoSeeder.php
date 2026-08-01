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
 * Fills the PLATFORM console (/admin) with a coherent four-month trading
 * history: a cash box, partners, subscription payments, running costs, partner
 * draws, one closed clearing run and a small support inbox.
 *
 * WHY THIS EXISTS
 * DatabaseSeeder only ever produced the platform admin, the package catalogue
 * and one tenant. Everything the admin console is actually built to show -
 * الصناديق, الشركاء, المدفوعات, المالية, المقاصة, رسائل التواصل - came up empty
 * on a fresh install, so half the app could not be demoed or eyeballed at all.
 *
 * IT GOES THROUGH THE SERVICES, NOT THE MODELS
 * `Box.box_balance` is not maintained by the models: PaymentService increments
 * it, ExpenseService and WithdrawalService decrement it (and refuse to overdraw).
 * Creating rows with `Payment::create()` would leave every box balance wrong and
 * the finance page reporting numbers that do not add up. Same reasoning as
 * DemoTenantSeeder reusing TenantProvisioner rather than hand-rolling a tenant.
 *
 * A consequence: the services stamp `created_at` with now(), while a clearing
 * run attributes payments and withdrawals BY `created_at` (expenses by their own
 * `date` column - see SettlementService). Every row is therefore back-dated
 * explicitly after it is created, or the whole history would land in today and
 * the settlement would have nothing to settle.
 *
 * ORDER MATTERS: money in before money out. ExpenseService and WithdrawalService
 * throw InsufficientBoxBalanceException rather than let a box go negative, so
 * all payments for a month are recorded before that month's costs and draws.
 *
 * Idempotent: guarded on the demo box's name, so re-running `db:seed` never
 * doubles the history. Anything already in the database (manual test rows) is
 * left untouched and simply coexists.
 */
class PlatformDemoSeeder extends Seeder
{
    private const BOX_NAME = 'الصندوق الرئيسي';

    /**
     * Kept deliberately under 100% in TOTAL WITH whatever partners already
     * exist - SettlementService refuses to run when the percentages over-allocate
     * the period's profit, and the residual is what the platform retains.
     */
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
     * Four closed months plus the current one, so the finance page's monthly
     * trend has a shape and the clearing page has a period worth settling.
     *
     * @param  \Illuminate\Support\Collection<int, License>  $licenses
     * @param  \Illuminate\Support\Collection<int, Partner>  $partners
     */
    private function seedTrading(Box $box, $licenses, $partners): void
    {
        $payments = app(PaymentService::class);
        $expenses = app(ExpenseService::class);
        $withdrawals = app(WithdrawalService::class);

        /*
         * Costs are a SHARE of what actually came in that month, never fixed
         * figures. Most packages ship priced at 0 (Starter/Basic are free tiers),
         * so a hard-coded 335 of monthly running costs overdrew the box on the
         * very first month and ExpenseService - correctly - threw. Deriving them
         * keeps this seeder working whatever PackageSeeder charges, and keeps
         * the platform profitable, which is what makes a clearing run meaningful.
         */
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

                // Expenses carry their own back-datable `date`, which is what
                // the clearing run reads - no created_at fix-up needed.
                $expenses->create([
                    'boxId' => $box->id,
                    'description' => $description,
                    'amount' => $amount,
                    'date' => $month->copy()->addDays(6 + $i)->toDateString(),
                ]);
            }

            // --- money OUT: partner draws, skipping the open month so the
            //     current period still shows something owed ------------------
            if ($monthsAgo === 0) {
                continue;
            }

            foreach ($partners as $i => $partner) {
                $draw = round($income * $drawShareOfProfit * ((float) $partner->percentage / 100), 2);

                // Never overdraw: the service throws rather than go negative,
                // and rounding could still put the last draw over the edge.
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
     * One CONFIRMED run closing everything up to the end of last month, which
     * leaves the current month open - the state the clearing page is designed
     * to be looked at in.
     *
     * Skipped rather than forced when it cannot run cleanly: the percentages may
     * already over-allocate because of partners this seeder did not create, and
     * only one draft may exist at a time.
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

            /*
             * Matched on sender + subject rather than blindly created. This
             * seeder's own guard is the demo box, so anything that removes the
             * box - a re-seed after a clean-out - replays this method, and a
             * plain create() silently doubled the inbox every time.
             */
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

    /**
     * Only the keys the operator would realistically brand. The rest of
     * SettingRegistry keeps serving its own defaults, which is what an unset
     * key is supposed to do.
     */
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

    /**
     * Rewrites `created_at` past the service's now() stamp. Done with a query
     * builder update rather than the model so no touch/observer logic re-stamps
     * it on the way back out.
     */
    private function backdate(Payment|Withdrawal $model, Carbon $when): void
    {
        DB::table($model->getTable())->where('id', $model->id)->update([
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }
}
