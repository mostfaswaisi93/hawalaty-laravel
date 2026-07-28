@extends('layouts.app')

@section('title', __('طلب جديد - حوالاتي'))

@section('content')
    <div class="page-enter"
        x-data="orderBuilder({{ $menuJson }}, {{ \Illuminate\Support\Js::from($banks) }}, {{ \Illuminate\Support\Js::from($wallets) }})">

        <header class="mb-8">
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('طلب جديد') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('اختر الأصناف، حدّد طريقة الدفع، وأنشئ الطلب.') }}</p>
        </header>

        {{-- Connection state, stated before the order is written rather than
             after it fails - staff need to know the order will be held. --}}
        <div x-show="!$store.offline.online" x-cloak
            class="flex items-start gap-3 rounded-2xl border border-tertiary/40 bg-tertiary/10 p-4">
            <span class="material-symbols-outlined flex-shrink-0 text-tertiary">cloud_off</span>
            <div>
                <p class="font-body-lg font-bold text-on-surface">{{ __('لا يوجد اتصال') }}</p>
                <p class="font-body-sm text-on-surface-variant">{{ __('يمكنك متابعة تسجيل الطلبات - ستُحفظ على هذا الجهاز وتُرسل تلقائياً عند عودة الشبكة.') }}</p>
            </div>
        </div>

        <div x-show="queued" x-cloak
            class="flex items-start gap-3 rounded-2xl border border-success/40 bg-success/10 p-4">
            <span class="material-symbols-outlined flex-shrink-0 text-success">schedule_send</span>
            <p class="font-body-lg text-on-surface">{{ __('حُفظ الطلب على هذا الجهاز وسيُرسل عند عودة الاتصال.') }}</p>
        </div>

        <div x-show="queueFailed" x-cloak
            class="flex items-start gap-3 rounded-2xl border border-error/40 bg-error/10 p-4">
            <span class="material-symbols-outlined flex-shrink-0 text-error">error</span>
            <p class="font-body-lg text-on-surface">{{ __('تعذّر حفظ الطلب على هذا الجهاز. لا تُغلق الصفحة قبل عودة الاتصال.') }}</p>
        </div>

        <form method="POST" action="{{ route('transfers.store') }}" @submit="onSubmit"
            class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[1.7fr_1fr]">
            @csrf

            {{-- ══════════════ LEFT - menu browser ══════════════ --}}
            <section class="space-y-5">
                {{-- search --}}
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                    <input type="text" x-model="search" placeholder="{{ __('ابحث عن صنف...') }}" aria-label="{{ __('بحث') }}"
                        class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3.5 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
                </div>

                {{-- category chips (hidden while searching) --}}
                <div x-show="!search.trim() && menu.length" class="flex flex-wrap gap-2">
                    <template x-for="cat in menu" :key="cat.id">
                        <button type="button" @click="activeCat = cat.id"
                            class="rounded-full border px-4 py-2 font-body-sm font-bold transition-all"
                            :class="activeCat === cat.id
                                ? 'border-transparent bg-primary text-on-primary shadow-md'
                                : 'border-outline-variant bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container'"
                            x-text="cat.name"></button>
                    </template>
                </div>

                {{-- items grid --}}
                <template x-if="menu.length">
                    {{-- The entrance sits on the grid, not on the tiles: x-for rebuilds them on
                         every search keystroke, so a per-tile cascade would replay as the user
                         types - and a filled entrance animation outranks .press on :active. --}}
                    <div class="reveal-up grid grid-cols-2 gap-3 sm:grid-cols-3" style="--d:80ms">
                        <template x-for="item in filteredItems" :key="item.id">
                            <button type="button" @click="addItem(item)"
                                class="press tonal-elevation group relative flex flex-col justify-between gap-3 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-4 text-start transition-all hover:border-primary">
                                <span x-show="qtyOf(item.id) > 0"
                                    class="absolute end-3 top-3 flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-1.5 font-numeral-lg text-xs font-bold text-on-primary"
                                    x-text="qtyOf(item.id)" x-cloak></span>
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-on-primary">
                                    <span class="material-symbols-outlined">lunch_dining</span>
                                </div>
                                <div>
                                    <p class="font-body-lg font-bold leading-tight text-on-surface" x-text="item.name"></p>
                                    <p class="mt-1 font-body-sm text-primary" dir="ltr"><span x-text="fmt(item.price)"></span> ₪</p>
                                </div>
                            </button>
                        </template>

                        {{-- no search results --}}
                        <div x-show="!filteredItems.length" class="col-span-full flex flex-col items-center gap-2 py-16 text-center text-outline">
                            <span class="material-symbols-outlined !text-5xl">search_off</span>
                            <p>{{ __('لا توجد أصناف مطابقة') }}</p>
                        </div>
                    </div>
                </template>

                {{-- empty menu state --}}
                <template x-if="!menu.length">
                    <div class="reveal-scale flex flex-col items-center gap-3 rounded-3xl border border-dashed border-outline-variant bg-surface-container-lowest py-20 text-center">
                        <span class="material-symbols-outlined !text-5xl text-outline/60">restaurant_menu</span>
                        <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أصناف في القائمة بعد') }}</p>
                        <a href="/menu" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('أضف أصنافاً إلى قائمتك أولاً') }}">{{ __('أضف أصنافاً إلى قائمتك أولاً') }}</a>
                    </div>
                </template>
            </section>

            {{-- ══════════════ RIGHT - order summary ══════════════ --}}
            <aside class="space-y-5 lg:sticky lg:top-24">
                <div class="reveal-up tonal-elevation overflow-hidden rounded-3xl border border-outline-variant/50 bg-surface-container-lowest" style="--d:120ms">
                    <div class="flex items-center justify-between border-b border-outline-variant/50 p-5">
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('الطلب الحالي') }}</h2>
                        <span x-show="itemCount > 0" x-cloak
                            class="rounded-full bg-secondary-container/20 px-3 py-1 font-body-sm font-bold text-secondary">
                            <span x-text="itemCount"></span> {{ __('صنف') }}
                        </span>
                    </div>

                    {{-- cart lines --}}
                    <div class="stagger max-h-72 space-y-2 overflow-y-auto p-4">
                        <div x-show="!cart.length" class="flex flex-col items-center gap-2 py-10 text-center text-outline">
                            <span class="material-symbols-outlined !text-4xl">shopping_cart</span>
                            <p class="font-body-sm">{{ __('لم تُضِف أي صنف بعد') }}</p>
                        </div>

                        <template x-for="line in cart" :key="line.id">
                            <div class="flex items-center gap-3 rounded-2xl bg-surface-container-low p-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-body-sm font-bold text-on-surface" x-text="line.name"></p>
                                    <p class="text-[11px] text-on-surface-variant" dir="ltr"><span x-text="fmt(line.price)"></span> ₪</p>
                                </div>
                                {{-- 40px targets, not 28px: this is the busiest screen in the app and it is
                                     used on phones, where decrement doubles as delete once qty hits 1 -
                                     a mis-tap destroys the line. Wider gap for the same reason. --}}
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="dec(line)" aria-label="{{ __('إنقاص') }}" title="{{ __('إنقاص') }}"
                                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                        <span class="material-symbols-outlined !text-lg" x-text="line.qty > 1 ? 'remove' : 'delete'"></span>
                                    </button>
                                    <span class="w-6 text-center font-numeral-lg font-bold text-on-surface" x-text="line.qty"></span>
                                    <button type="button" @click="inc(line)" aria-label="{{ __('زيادة') }}" title="{{ __('زيادة') }}"
                                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors hover:bg-primary hover:text-on-primary">
                                        <span class="material-symbols-outlined !text-lg">add</span>
                                    </button>
                                </div>
                                <p class="w-20 text-end font-body-sm font-bold text-primary" dir="ltr"><span x-text="fmt(line.price * line.qty)"></span> ₪</p>
                            </div>
                        </template>
                    </div>

                    {{-- customer + payment --}}
                    <div class="space-y-5 border-t border-outline-variant/50 p-5">
                        <div class="space-y-2">
                            <label for="customer" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم العميل') }}</label>
                            <input id="customer" name="customer" x-model="customer" list="customer-names" placeholder="{{ __('مثال: أحمد أبو ياسين') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
                            <datalist id="customer-names">
                                @foreach ($customerNames as $name)
                                    <option value="{{ $name }}"></option>
                                @endforeach
                            </datalist>
                        </div>

                        <div class="space-y-2">
                            <label for="customerPhone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">
                                {{ __('هاتف العميل') }} <span class="normal-case text-outline">({{ __('اختياري') }})</span>
                            </label>
                            <input id="customerPhone" name="customerPhone" x-model="customerPhone" dir="ltr" placeholder="059..."
                                aria-describedby="customerPhone-hint"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
                            {{--
                                The field stays OPTIONAL on purpose. The phone is the
                                customer's identity, so requiring it here would push staff
                                to type a placeholder to get past the form - and two people
                                typing the same placeholder would merge two real customers
                                into one record. A blank is honest; a fake number is not.
                                The consequence is stated instead of enforced.
                            --}}
                            <p id="customerPhone-hint" x-show="!customerPhone.trim()" x-cloak
                                class="flex items-start gap-1.5 font-body-sm text-body-sm text-on-surface-variant">
                                <span class="material-symbols-outlined !text-base">info</span>
                                {{ __('بدون رقم، لن يُربط هذا الطلب بملف عميل.') }}
                            </p>
                        </div>

                        {{-- payment method segmented --}}
                        <div class="space-y-2">
                            <span class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('طريقة الدفع') }}</span>
                            <div class="grid grid-cols-2 gap-2 rounded-xl bg-surface-container p-1">
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg py-2.5 font-body-sm font-bold transition-all"
                                    :class="paymentMethod === 'cash' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'">
                                    <input type="radio" name="paymentMethod" value="cash" x-model="paymentMethod" class="sr-only" />
                                    <span class="material-symbols-outlined !text-xl">payments</span>
                                    {{ __('نقدي') }}
                                </label>
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg py-2.5 font-body-sm font-bold transition-all"
                                    :class="paymentMethod === 'bank' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'">
                                    <input type="radio" name="paymentMethod" value="bank" x-model="paymentMethod" class="sr-only" />
                                    <span class="material-symbols-outlined !text-xl">account_balance</span>
                                    {{ __('تحويل بنكي') }}
                                </label>
                            </div>
                        </div>

                        {{-- ── CASH scenario ── --}}
                        <div x-show="paymentMethod === 'cash'" x-cloak class="space-y-4">
                            <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3">
                                <span class="font-body-sm font-bold text-on-surface">{{ __('يوجد باقٍ للعميل؟') }}</span>
                                <input type="checkbox" x-model="hasChange" class="h-5 w-5 rounded accent-primary" />
                            </label>
                            <input type="hidden" name="hasChange" :value="(paymentMethod === 'cash' && hasChange) ? 1 : 0" />

                            <div x-show="hasChange" x-cloak class="space-y-4 rounded-xl border border-outline-variant/60 bg-surface-container-low p-4">
                                <div class="grid grid-cols-2 gap-2 rounded-xl bg-surface-container p-1">
                                    <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg py-2 font-body-sm font-bold transition-all"
                                        :class="changeMethod === 'cash' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'">
                                        <input type="radio" name="changeMethod" value="cash" x-model="changeMethod" class="sr-only" />
                                        {{ __('باقٍ نقدي') }}
                                    </label>
                                    <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg py-2 font-body-sm font-bold transition-all"
                                        :class="changeMethod === 'bank' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'">
                                        <input type="radio" name="changeMethod" value="bank" x-model="changeMethod" class="sr-only" />
                                        {{ __('باقٍ للبنك') }}
                                    </label>
                                </div>

                                <div x-show="changeMethod === 'bank'" x-cloak class="space-y-2">
                                    <label for="transfers-change-bank-id" class="block font-body-sm text-on-surface-variant">{{ __('البنك / المحفظة') }}</label>
                                    <select id="transfers-change-bank-id" name="changeBankId" x-model="changeBankId"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                        <option value="">{{ __('اختر...') }}</option>
                                        <template x-for="b in allBanks" :key="b.id">
                                            <option :value="b.id" x-text="b.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <label for="transfers-change-amount" class="block font-body-sm text-on-surface-variant">{{ __('قيمة الباقي (₪)') }}</label>
                                    <input id="transfers-change-amount" type="number" step="0.01" min="0" name="changeAmount" x-model="changeAmount" dir="ltr" placeholder="0.00"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>
                            </div>
                        </div>

                        {{-- ── BANK scenario ── --}}
                        <div x-show="paymentMethod === 'bank'" x-cloak class="space-y-4">
                            <div class="space-y-2">
                                <label for="transfers-bank-id" class="block font-body-sm text-on-surface-variant">{{ __('البنك / المحفظة') }}</label>
                                <select id="transfers-bank-id" name="bankId" x-model="bankId"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                    <option value="">{{ __('اختر...') }}</option>
                                    <optgroup label="{{ __('البنوك') }}">
                                        <template x-for="b in banks" :key="b.id">
                                            <option :value="b.id" x-text="b.name"></option>
                                        </template>
                                    </optgroup>
                                    <optgroup label="{{ __('المحافظ') }}">
                                        <template x-for="b in wallets" :key="b.id">
                                            <option :value="b.id" x-text="b.name"></option>
                                        </template>
                                    </optgroup>
                                </select>
                            </div>
                            {{-- "sender name" / "sender phone" were removed from this form: the
                                 operator does not need to re-key who paid, and neither field is
                                 used by the WhatsApp order notification. The columns remain on
                                 `transfers` so historical orders keep their values, and the API
                                 still accepts both for backwards compatibility. --}}
                            <div class="space-y-2">
                                <label for="transfers-transferred-amount" class="block font-body-sm text-on-surface-variant">{{ __('المبلغ المُحوَّل (₪)') }}</label>
                                <input id="transfers-transferred-amount" type="number" step="0.01" min="0" name="transferredAmount" x-model="transferredAmount" dir="ltr" placeholder="0.00"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>
                        </div>

                        {{-- total + submit --}}
                        <div class="border-t border-outline-variant/50 pt-4">
                            <div class="mb-4 flex items-end justify-between">
                                <span class="font-body-lg text-on-surface-variant">{{ __('الإجمالي') }}</span>
                                <span class="font-display-lg text-3xl font-bold text-primary" dir="ltr"><span x-text="fmt(total)"></span> ₪</span>
                            </div>
                            <button type="submit" :disabled="!canSubmit || submitting"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50">
                                <span class="material-symbols-outlined" x-show="!submitting">check_circle</span>
                                <span x-text="submitting ? '{{ __('جارٍ الإنشاء...') }}' : '{{ __('إنشاء الطلب') }}'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- serialized cart line items --}}
                <template x-for="(line, idx) in cart" :key="'in-' + line.id">
                    <div class="hidden">
                        <input type="hidden" :name="`items[${idx}][itemId]`" :value="line.id" />
                        <input type="hidden" :name="`items[${idx}][name]`" :value="line.name" />
                        <input type="hidden" :name="`items[${idx}][quantity]`" :value="line.qty" />
                        <input type="hidden" :name="`items[${idx}][price]`" :value="line.price" />
                    </div>
                </template>
            </aside>
        </form>
    </div>

    <script>
        window.orderBuilder = function (menu, banks, wallets) {
            return {
                menu: menu, banks: banks, wallets: wallets,
                search: '',
                activeCat: menu.length ? menu[0].id : null,
                cart: [],
                customer: '',
                customerPhone: '',
                paymentMethod: 'cash',
                hasChange: false,
                changeMethod: 'cash',
                changeBankId: '',
                changeAmount: '',
                bankId: '',
                transferredAmount: '',
                submitting: false,
                queued: false,
                queueFailed: false,

                init() {
                    // Reset the fields of the scenario the user just left so a
                    // stale value never gets submitted (required_if rules key
                    // off the *submitted* value, not what is visible).
                    this.$watch('paymentMethod', () => {
                        this.hasChange = false;
                        this.changeMethod = 'cash';
                        this.changeBankId = '';
                        this.changeAmount = '';
                        this.bankId = '';
                        this.transferredAmount = this.paymentMethod === 'bank' ? this.total.toFixed(2) : '';
                    });
                },

                get allBanks() { return this.banks.concat(this.wallets); },

                get filteredItems() {
                    const term = this.search.trim();
                    const out = [];
                    this.menu.forEach(cat => {
                        cat.items.forEach(item => {
                            if (term ? item.name.includes(term) : cat.id === this.activeCat) {
                                out.push(item);
                            }
                        });
                    });
                    return out;
                },

                get total() { return this.cart.reduce((sum, l) => sum + l.price * l.qty, 0); },
                get itemCount() { return this.cart.reduce((sum, l) => sum + l.qty, 0); },

                qtyOf(id) { const l = this.cart.find(c => c.id === id); return l ? l.qty : 0; },
                addItem(item) {
                    const existing = this.cart.find(c => c.id === item.id);
                    if (existing) { existing.qty++; }
                    else { this.cart.push({ id: item.id, name: item.name, price: item.price, qty: 1 }); }
                    if (this.paymentMethod === 'bank') { this.transferredAmount = this.total.toFixed(2); }
                },
                inc(line) { line.qty++; if (this.paymentMethod === 'bank') this.transferredAmount = this.total.toFixed(2); },
                dec(line) {
                    line.qty--;
                    if (line.qty <= 0) { this.cart = this.cart.filter(c => c !== line); }
                    if (this.paymentMethod === 'bank') this.transferredAmount = this.total.toFixed(2);
                },

                fmt(n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

                get canSubmit() {
                    if (!this.customer.trim() || !this.cart.length) return false;
                    if (this.paymentMethod === 'bank') {
                        if (!this.bankId || !this.transferredAmount) return false;
                    }
                    if (this.paymentMethod === 'cash' && this.hasChange) {
                        if (this.changeAmount === '' || this.changeAmount === null) return false;
                        if (this.changeMethod === 'bank' && !this.changeBankId) return false;
                    }
                    return true;
                },

                /**
                 * Exactly what StoreTransferRequest validates, so a queued order
                 * is checked by the same rules as one posted online - the server
                 * still recomputes the amount from the catalogue either way.
                 */
                /**
                 * Keys that do not apply are OMITTED, never sent as null.
                 * `changeMethod` and friends are validated with Rule::in without
                 * `nullable`, which passes over an absent key but rejects an
                 * explicit null - so sending nulls made every cash order without
                 * change fail validation on arrival, hours after it was taken.
                 */
                orderPayload() {
                    const payload = {
                        customer: this.customer.trim(),
                        paymentMethod: this.paymentMethod,
                        items: this.cart.map(l => ({
                            itemId: l.id,
                            name: l.name,
                            quantity: l.qty,
                            price: l.price,
                        })),
                    };

                    if (this.customerPhone.trim()) payload.customerPhone = this.customerPhone.trim();

                    if (this.paymentMethod === 'cash') {
                        payload.hasChange = this.hasChange;

                        if (this.hasChange) {
                            payload.changeMethod = this.changeMethod;
                            payload.changeAmount = this.changeAmount;
                            if (this.changeMethod === 'bank') payload.changeBankId = this.changeBankId;
                        }
                    } else {
                        payload.bankId = this.bankId;
                        payload.transferredAmount = this.transferredAmount;
                    }

                    return payload;
                },

                resetOrder() {
                    this.cart = [];
                    this.customer = '';
                    this.customerPhone = '';
                    this.hasChange = false;
                    this.changeMethod = 'cash';
                    this.changeBankId = '';
                    this.changeAmount = '';
                    this.bankId = '';
                    this.transferredAmount = '';
                    this.submitting = false;
                },

                async onSubmit(e) {
                    if (!this.canSubmit) { e.preventDefault(); return; }

                    // Online: let the form post normally. The offline path is a
                    // fallback, never the default - an order that can reach the
                    // server now should, so the kitchen sees it now.
                    if (navigator.onLine) {
                        this.submitting = true;
                        return;
                    }

                    e.preventDefault();
                    this.submitting = true;

                    try {
                        await Alpine.store('offline').queue(this.orderPayload());
                        this.queued = true;
                        this.resetOrder();
                        setTimeout(() => { this.queued = false; }, 6000);
                    } catch {
                        // Storage refused - private mode, or the disk is full.
                        // Say so rather than silently losing a sale.
                        this.queueFailed = true;
                        this.submitting = false;
                    }
                },
            };
        };
    </script>
@endsection
