@extends('layouts.app')

@section('title', 'طلب جديد — حوالاتي')

@section('content')
    <div class="page-enter"
        x-data="orderBuilder({{ $menuJson }}, {{ \Illuminate\Support\Js::from($banks) }}, {{ \Illuminate\Support\Js::from($wallets) }})">

        <header class="mb-8">
            <h1 class="font-display-lg text-display-lg text-on-surface">طلب جديد</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">اختر الأصناف، حدّد طريقة الدفع، وأنشئ الطلب.</p>
        </header>

        <form method="POST" action="{{ route('transfers.store') }}" @submit="onSubmit"
            class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[1.7fr_1fr]">
            @csrf

            {{-- ══════════════ LEFT — menu browser ══════════════ --}}
            <section class="space-y-5">
                {{-- search --}}
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute top-1/2 right-0 -translate-y-1/2 pr-4 text-outline">search</span>
                    <input type="text" x-model="search" placeholder="ابحث عن صنف..."
                        class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3.5 pr-12 pl-4 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
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
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <template x-for="item in filteredItems" :key="item.id">
                            <button type="button" @click="addItem(item)"
                                class="tonal-elevation group relative flex flex-col justify-between gap-3 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-4 text-start transition-all hover:border-primary">
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
                            <p>لا توجد أصناف مطابقة</p>
                        </div>
                    </div>
                </template>

                {{-- empty menu state --}}
                <template x-if="!menu.length">
                    <div class="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-outline-variant bg-surface-container-lowest py-20 text-center">
                        <span class="material-symbols-outlined !text-5xl text-outline/60">restaurant_menu</span>
                        <p class="font-body-lg text-on-surface-variant">لا توجد أصناف في القائمة بعد</p>
                        <a href="/menu" class="font-body-sm font-bold text-primary hover:underline">أضف أصنافاً إلى قائمتك أولاً</a>
                    </div>
                </template>
            </section>

            {{-- ══════════════ RIGHT — order summary ══════════════ --}}
            <aside class="space-y-5 lg:sticky lg:top-24">
                <div class="tonal-elevation overflow-hidden rounded-3xl border border-outline-variant/50 bg-surface-container-lowest">
                    <div class="flex items-center justify-between border-b border-outline-variant/50 p-5">
                        <h2 class="font-headline-md text-headline-md text-on-surface">الطلب الحالي</h2>
                        <span x-show="itemCount > 0" x-cloak
                            class="rounded-full bg-secondary-container/20 px-3 py-1 font-body-sm font-bold text-secondary">
                            <span x-text="itemCount"></span> صنف
                        </span>
                    </div>

                    {{-- cart lines --}}
                    <div class="max-h-72 space-y-2 overflow-y-auto p-4">
                        <div x-show="!cart.length" class="flex flex-col items-center gap-2 py-10 text-center text-outline">
                            <span class="material-symbols-outlined !text-4xl">shopping_cart</span>
                            <p class="font-body-sm">لم تُضِف أي صنف بعد</p>
                        </div>

                        <template x-for="line in cart" :key="line.id">
                            <div class="flex items-center gap-3 rounded-2xl bg-surface-container-low p-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-body-sm font-bold text-on-surface" x-text="line.name"></p>
                                    <p class="text-[11px] text-outline" dir="ltr"><span x-text="fmt(line.price)"></span> ₪</p>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" @click="dec(line)" aria-label="إنقاص"
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                        <span class="material-symbols-outlined !text-lg" x-text="line.qty > 1 ? 'remove' : 'delete'"></span>
                                    </button>
                                    <span class="w-6 text-center font-numeral-lg font-bold text-on-surface" x-text="line.qty"></span>
                                    <button type="button" @click="inc(line)" aria-label="زيادة"
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors hover:bg-primary hover:text-on-primary">
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
                            <label for="customer" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">اسم العميل</label>
                            <input id="customer" name="customer" x-model="customer" placeholder="مثال: أحمد أبو ياسين"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>

                        {{-- payment method segmented --}}
                        <div class="space-y-2">
                            <span class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">طريقة الدفع</span>
                            <div class="grid grid-cols-2 gap-2 rounded-xl bg-surface-container p-1">
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg py-2.5 font-body-sm font-bold transition-all"
                                    :class="paymentMethod === 'cash' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'">
                                    <input type="radio" name="paymentMethod" value="cash" x-model="paymentMethod" class="sr-only" />
                                    <span class="material-symbols-outlined !text-xl">payments</span>
                                    نقدي
                                </label>
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg py-2.5 font-body-sm font-bold transition-all"
                                    :class="paymentMethod === 'bank' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'">
                                    <input type="radio" name="paymentMethod" value="bank" x-model="paymentMethod" class="sr-only" />
                                    <span class="material-symbols-outlined !text-xl">account_balance</span>
                                    تحويل بنكي
                                </label>
                            </div>
                        </div>

                        {{-- ── CASH scenario ── --}}
                        <div x-show="paymentMethod === 'cash'" x-cloak class="space-y-4">
                            <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3">
                                <span class="font-body-sm font-bold text-on-surface">يوجد باقٍ للعميل؟</span>
                                <input type="checkbox" x-model="hasChange" class="h-5 w-5 rounded accent-primary" />
                            </label>
                            <input type="hidden" name="hasChange" :value="(paymentMethod === 'cash' && hasChange) ? 1 : 0" />

                            <div x-show="hasChange" x-cloak class="space-y-4 rounded-xl border border-outline-variant/60 bg-surface-container-low p-4">
                                <div class="grid grid-cols-2 gap-2 rounded-xl bg-surface-container p-1">
                                    <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg py-2 font-body-sm font-bold transition-all"
                                        :class="changeMethod === 'cash' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'">
                                        <input type="radio" name="changeMethod" value="cash" x-model="changeMethod" class="sr-only" />
                                        باقٍ نقدي
                                    </label>
                                    <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg py-2 font-body-sm font-bold transition-all"
                                        :class="changeMethod === 'bank' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'">
                                        <input type="radio" name="changeMethod" value="bank" x-model="changeMethod" class="sr-only" />
                                        باقٍ للبنك
                                    </label>
                                </div>

                                <div x-show="changeMethod === 'bank'" x-cloak class="space-y-2">
                                    <label class="block font-body-sm text-on-surface-variant">البنك / المحفظة</label>
                                    <select name="changeBankId" x-model="changeBankId"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                        <option value="">اختر...</option>
                                        <template x-for="b in allBanks" :key="b.id">
                                            <option :value="b.id" x-text="b.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <label class="block font-body-sm text-on-surface-variant">قيمة الباقي (₪)</label>
                                    <input type="number" step="0.01" min="0" name="changeAmount" x-model="changeAmount" dir="ltr" placeholder="0.00"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>
                            </div>
                        </div>

                        {{-- ── BANK scenario ── --}}
                        <div x-show="paymentMethod === 'bank'" x-cloak class="space-y-4">
                            <div class="space-y-2">
                                <label class="block font-body-sm text-on-surface-variant">البنك / المحفظة</label>
                                <select name="bankId" x-model="bankId"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                    <option value="">اختر...</option>
                                    <optgroup label="البنوك">
                                        <template x-for="b in banks" :key="b.id">
                                            <option :value="b.id" x-text="b.name"></option>
                                        </template>
                                    </optgroup>
                                    <optgroup label="المحافظ">
                                        <template x-for="b in wallets" :key="b.id">
                                            <option :value="b.id" x-text="b.name"></option>
                                        </template>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-2">
                                    <label class="block font-body-sm text-on-surface-variant">اسم المُرسِل</label>
                                    <input name="senderName" x-model="senderName" placeholder="اختياري"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block font-body-sm text-on-surface-variant">هاتف المُرسِل</label>
                                    <input name="senderPhone" x-model="senderPhone" dir="ltr" placeholder="059..."
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="block font-body-sm text-on-surface-variant">المبلغ المُحوَّل (₪)</label>
                                <input type="number" step="0.01" min="0" name="transferredAmount" x-model="transferredAmount" dir="ltr" placeholder="0.00"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>
                        </div>

                        {{-- total + submit --}}
                        <div class="border-t border-outline-variant/50 pt-4">
                            <div class="mb-4 flex items-end justify-between">
                                <span class="font-body-lg text-on-surface-variant">الإجمالي</span>
                                <span class="font-display-lg text-3xl font-bold text-primary" dir="ltr"><span x-text="fmt(total)"></span> ₪</span>
                            </div>
                            <button type="submit" :disabled="!canSubmit || submitting"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 font-headline-md text-headline-md text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50">
                                <span class="material-symbols-outlined" x-show="!submitting">check_circle</span>
                                <span x-text="submitting ? 'جارٍ الإنشاء...' : 'إنشاء الطلب'"></span>
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
                paymentMethod: 'cash',
                hasChange: false,
                changeMethod: 'cash',
                changeBankId: '',
                changeAmount: '',
                bankId: '',
                senderName: '',
                senderPhone: '',
                transferredAmount: '',
                submitting: false,

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
                        this.senderName = '';
                        this.senderPhone = '';
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

                onSubmit(e) {
                    if (!this.canSubmit) { e.preventDefault(); return; }
                    this.submitting = true;
                },
            };
        };
    </script>
@endsection
