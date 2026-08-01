@extends('layouts.app')

@section('title', __('طلب جديد - حوالاتي'))

@section('content')
    <div class="page-enter"
        x-data="orderBuilder({{ $menuJson }}, {{ \Illuminate\Support\Js::from($accounts) }}, {{ \Illuminate\Support\Js::from([
            ['key' => 'cash', 'icon' => 'payments', 'label' => __('نقدي')],
            ['key' => 'bank', 'icon' => 'account_balance', 'label' => __('بنك')],
            ['key' => 'wallet', 'icon' => 'account_balance_wallet', 'label' => __('محفظة')],
        ]) }})">

        <header class="mb-8">
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('طلب جديد') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('اختر الأصناف، حدّد طريقة الدفع، وأنشئ الطلب.') }}</p>
        </header>

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

            <section class="space-y-5">
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                    <input type="text" x-model="search" placeholder="{{ __('ابحث عن صنف...') }}" aria-label="{{ __('بحث') }}"
                        class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3.5 text-body-lg outline-none transition-all focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
                </div>

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

                <template x-if="menu.length">
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

                        <div x-show="!filteredItems.length" class="col-span-full flex flex-col items-center gap-2 py-16 text-center text-outline">
                            <span class="material-symbols-outlined !text-5xl">search_off</span>
                            <p>{{ __('لا توجد أصناف مطابقة') }}</p>
                        </div>
                    </div>
                </template>

                <template x-if="!menu.length">
                    <div class="reveal-scale flex flex-col items-center gap-3 rounded-3xl border border-dashed border-outline-variant bg-surface-container-lowest py-20 text-center">
                        <span class="material-symbols-outlined !text-5xl text-outline/60">restaurant_menu</span>
                        <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أصناف في القائمة بعد') }}</p>
                        <a href="/menu" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('أضف أصنافاً إلى قائمتك أولاً') }}">{{ __('أضف أصنافاً إلى قائمتك أولاً') }}</a>
                    </div>
                </template>
            </section>

            <aside class="space-y-5 lg:sticky lg:top-24">
                <div class="reveal-up tonal-elevation overflow-hidden rounded-3xl border border-outline-variant/50 bg-surface-container-lowest" style="--d:120ms">
                    <div class="flex items-center justify-between border-b border-outline-variant/50 p-5">
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('الطلب الحالي') }}</h2>
                        <span x-show="itemCount > 0" x-cloak
                            class="rounded-full bg-secondary-container/20 px-3 py-1 font-body-sm font-bold text-secondary">
                            <span x-text="itemCount"></span> {{ __('صنف') }}
                        </span>
                    </div>

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
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="dec(line)" aria-label="{{ __('إنقاص') }}" title="{{ __('إنقاص') }}"
                                        @mouseenter="showTipBelow($el, @js(__('إنقاص')))" @mouseleave="hideTip()"
                                        @focus="showTipBelow($el, @js(__('إنقاص')))" @blur="hideTip()"
                                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                        <span class="material-symbols-outlined !text-lg" x-text="line.qty > 1 ? 'remove' : 'delete'"></span>
                                    </button>
                                    <span class="w-6 text-center font-numeral-lg font-bold text-on-surface" x-text="line.qty"></span>
                                    <button type="button" @click="inc(line)" aria-label="{{ __('زيادة') }}" title="{{ __('زيادة') }}"
                                        @mouseenter="showTipBelow($el, @js(__('زيادة')))" @mouseleave="hideTip()"
                                        @focus="showTipBelow($el, @js(__('زيادة')))" @blur="hideTip()"
                                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors hover:bg-primary hover:text-on-primary">
                                        <span class="material-symbols-outlined !text-lg">add</span>
                                    </button>
                                </div>
                                <p class="w-20 text-end font-body-sm font-bold text-primary" dir="ltr"><span x-text="fmt(line.price * line.qty)"></span> ₪</p>
                            </div>
                        </template>
                    </div>

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
                            <p id="customerPhone-hint" x-show="!customerPhone.trim()" x-cloak
                                class="flex items-start gap-1.5 font-body-sm text-body-sm text-on-surface-variant">
                                <span class="material-symbols-outlined !text-base">info</span>
                                {{ __('بدون رقم، لن يُربط هذا الطلب بملف عميل.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label for="orderNotes" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">
                                {{ __('ملاحظات الطلب') }} <span class="text-outline">({{ __('اختياري') }})</span>
                            </label>

                            @if ($constantLists->isNotEmpty())
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($constantLists as $list)
                                        <select @change="appendNote($event.target.value); $event.target.selectedIndex = 0"
                                            aria-label="{{ $list['title'] }}"
                                            class="min-w-40 flex-1 rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 font-body-sm text-body-sm outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                            <option value="">{{ $list['title'] }}</option>
                                            @foreach ($list['values'] as $value)
                                                <option value="{{ $value }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    @endforeach
                                </div>
                            @endif

                            <textarea id="orderNotes" name="notes" x-model="notes" rows="2" maxlength="500"
                                placeholder="{{ __('مثال: بدون بصل، توصيل داخل المدينة') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 font-body-sm text-body-sm outline-none focus:border-transparent focus:ring-2 focus:ring-primary"></textarea>
                            @error('notes')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-2">
                            <span class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('طريقة الدفع') }}</span>
                            <input type="hidden" name="paymentMethod" :value="paymentMethod" />
                            <div class="grid grid-cols-3 gap-2 rounded-xl bg-surface-container p-1">
                                <template x-for="opt in payTargets" :key="opt.key">
                                    <button type="button" @click="payTarget = opt.key"
                                        class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg py-2.5 font-body-sm font-bold transition-all"
                                        :class="payTarget === opt.key ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'"
                                        :aria-pressed="payTarget === opt.key ? 'true' : 'false'">
                                        <span class="material-symbols-outlined !text-xl" x-text="opt.icon"></span>
                                        <span x-text="opt.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div x-show="paymentMethod === 'cash'" x-cloak class="space-y-4">
                            <div class="space-y-2">
                                <span class="block font-body-sm text-on-surface-variant">{{ __('اختر الصندوق النقدي') }}</span>
                                @include('partials._account-picker', [
                                    'model' => 'accountId',
                                    'inputName' => 'accountId',
                                    'listExpr' => "accountsOfKind('cash')",
                                    'id' => 'cashbox',
                                    'empty' => __('لا توجد صناديق نقدية. أضِف صندوقاً من الكتالوجات ثم افتح له حساباً.'),
                                ])
                            </div>

                        </div>

                        <div x-show="paymentMethod === 'cash'" x-cloak class="space-y-2">
                            <span class="block font-body-sm text-on-surface-variant">{{ __('إجمالي الفاتورة') }}</span>
                            <div class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                                <span class="font-body-lg font-bold text-on-surface" dir="ltr"><span x-text="fmt(subtotal)"></span> <span x-text="currencySymbol"></span></span>
                            </div>
                        </div>

                        <div x-show="paymentMethod === 'cash'" x-cloak class="space-y-2">
                            <label for="transfers-discount-cash" class="block font-body-sm text-on-surface-variant">{{ __('الخصم (اختياري)') }}</label>
                            <input id="transfers-discount-cash" type="number" step="0.01" min="0" :max="subtotal" name="discount" :disabled="paymentMethod !== 'cash'" x-model="discount" dir="ltr" placeholder="0.00"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('discount')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="paymentMethod === 'cash'" x-cloak class="space-y-2">
                            <span class="block font-body-sm text-on-surface-variant">{{ __('المبلغ بعد الخصم') }}</span>
                            <div class="rounded-xl border border-primary/40 bg-primary/5 px-4 py-3">
                                <span class="font-body-lg font-bold text-primary" dir="ltr"><span x-text="fmt(total)"></span> <span x-text="currencySymbol"></span></span>
                            </div>
                        </div>

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
                                    <span class="block font-body-sm text-on-surface-variant">{{ __('الحساب المستلِم للباقي') }}</span>
                                    @include('partials._account-picker', [
                                        'model' => 'changeAccountId',
                                        'inputName' => 'changeAccountId',
                                        'listExpr' => 'accounts',
                                        'id' => 'change',
                                        'empty' => __('لا توجد حسابات. أنشئ حساباً أولاً من صفحة الحسابات.'),
                                    ])
                                </div>

                                <div class="space-y-2">
                                    <label for="transfers-change-amount" class="block font-body-sm text-on-surface-variant">{{ __('قيمة الباقي (₪)') }}</label>
                                    <input id="transfers-change-amount" type="number" step="0.01" min="0" name="changeAmount" x-model="changeAmount" dir="ltr" placeholder="0.00"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>
                            </div>
                        </div>

                        <div x-show="paymentMethod === 'bank'" x-cloak class="space-y-4">
                            <div class="space-y-2">
                                <span class="block font-body-sm text-on-surface-variant"
                                    x-text="payTarget === 'wallet' ? '{{ __('اختر المحفظة') }}' : '{{ __('اختر الحساب البنكي') }}'"></span>
                                @include('partials._account-picker', [
                                    'model' => 'accountId',
                                    'inputName' => 'accountId',
                                    'listExpr' => 'accountsOfKind(payTarget)',
                                    'id' => 'main',
                                    'empty' => __('لا توجد حسابات من هذا النوع. أنشئ حساباً أولاً من صفحة الحسابات.'),
                                ])
                            </div>
                        </div>

                        <div x-show="paymentMethod === 'bank'" x-cloak class="space-y-2">
                            <span class="block font-body-sm text-on-surface-variant">{{ __('إجمالي الفاتورة') }}</span>
                            <div class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                                <span class="font-body-lg font-bold text-on-surface" dir="ltr"><span x-text="fmt(subtotal)"></span> <span x-text="currencySymbol"></span></span>
                            </div>
                        </div>

                        <div x-show="paymentMethod === 'bank'" x-cloak class="space-y-2">
                            <label for="transfers-discount-bank" class="block font-body-sm text-on-surface-variant">{{ __('الخصم (اختياري)') }}</label>
                            <input id="transfers-discount-bank" type="number" step="0.01" min="0" :max="subtotal" name="discount" :disabled="paymentMethod !== 'bank'" x-model="discount" dir="ltr" placeholder="0.00"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('discount')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="paymentMethod === 'bank'" x-cloak class="space-y-2">
                            <span class="block font-body-sm text-on-surface-variant">{{ __('المبلغ بعد الخصم') }}</span>
                            <div class="rounded-xl border border-primary/40 bg-primary/5 px-4 py-3">
                                <span class="font-body-lg font-bold text-primary" dir="ltr"><span x-text="fmt(total)"></span> <span x-text="currencySymbol"></span></span>
                            </div>
                        </div>

                        <div x-show="paymentMethod === 'bank'" x-cloak class="space-y-4">
                            <div class="space-y-2">
                                <label for="transfers-transferred-amount" class="block font-body-sm text-on-surface-variant">
                                    {{ __('المبلغ المُحوَّل') }} <span dir="ltr">(<span x-text="currencySymbol"></span>)</span>
                                </label>
                                <input id="transfers-transferred-amount" type="number" step="0.01" min="0" :max="total" name="transferredAmount" x-model="transferredAmount" dir="ltr" placeholder="0.00"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>

                            <div class="space-y-2">
                                <label for="transfers-reference" class="block font-body-sm text-on-surface-variant">{{ __('الرقم المرجعي للمعاملة') }}</label>
                                <input id="transfers-reference" name="referenceNumber" x-model="referenceNumber" dir="ltr" maxlength="100" placeholder="TRX-000123"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                @error('referenceNumber')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="border-t border-outline-variant/50 pt-4">
                            <template x-if="(parseFloat(discount) || 0) > 0">
                                <div class="mb-2 space-y-1">
                                    <div class="flex items-center justify-between font-body-sm text-on-surface-variant">
                                        <span>{{ __('المجموع الفرعي') }}</span>
                                        <span dir="ltr"><span x-text="fmt(subtotal)"></span> ₪</span>
                                    </div>
                                    <div class="flex items-center justify-between font-body-sm text-error">
                                        <span>{{ __('الخصم') }}</span>
                                        <span dir="ltr">−<span x-text="fmt(parseFloat(discount) || 0)"></span> ₪</span>
                                    </div>
                                </div>
                            </template>
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
        window.orderBuilder = function (menu, accounts, payTargets) {
            return {
                menu: menu, accounts: accounts, payTargets: payTargets,
                search: '',
                activeCat: menu.length ? menu[0].id : null,
                cart: [],
                customer: '',
                customerPhone: '',
                notes: '',

                payTarget: 'cash',
                hasChange: false,
                changeMethod: 'cash',
                changeAccountId: '',
                changeAmount: '',
                accountId: '',
                transferredAmount: '',
                referenceNumber: '',
                discount: '',
                submitting: false,
                queued: false,
                queueFailed: false,

                init() {

                    this.$watch('discount', () => {
                        if (this.paymentMethod === 'bank') this.transferredAmount = this.total.toFixed(2);
                    });

                    this.$watch('payTarget', () => {
                        this.hasChange = false;
                        this.changeMethod = 'cash';
                        this.changeAccountId = '';
                        this.changeAmount = '';
                        this.accountId = '';
                        this.transferredAmount = this.paymentMethod === 'bank' ? this.total.toFixed(2) : '';
                    });
                },

                get paymentMethod() { return this.payTarget === 'cash' ? 'cash' : 'bank'; },

                accountsOfKind(kind) { return this.accounts.filter(a => a.kind === kind); },
                accountById(id) { return this.accounts.find(a => a.id === id) || null; },

                get currencySymbol() { return this.accountById(this.accountId)?.currency || '₪'; },

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

                get subtotal() { return this.cart.reduce((sum, l) => sum + l.price * l.qty, 0); },

                get total() { return Math.max(this.subtotal - (parseFloat(this.discount) || 0), 0); },
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
                        if (!this.accountId || !this.transferredAmount) return false;
                        if (!this.referenceNumber.trim()) return false;
                    }
                    if (this.paymentMethod === 'cash' && this.hasChange) {
                        if (this.changeAmount === '' || this.changeAmount === null) return false;
                        if (this.changeMethod === 'bank' && !this.changeAccountId) return false;
                    }
                    return true;
                },

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

                    if (this.discount !== '' && parseFloat(this.discount) > 0) payload.discount = this.discount;

                    if (this.customerPhone.trim()) payload.customerPhone = this.customerPhone.trim();
                    if (this.notes.trim()) payload.notes = this.notes.trim();

                    if (this.paymentMethod === 'cash') {
                        payload.hasChange = this.hasChange;

                        if (this.hasChange) {
                            payload.changeMethod = this.changeMethod;
                            payload.changeAmount = this.changeAmount;
                            if (this.changeMethod === 'bank') payload.changeAccountId = this.changeAccountId;
                        }
                    } else {
                        payload.accountId = this.accountId;
                        payload.transferredAmount = this.transferredAmount;
                        payload.referenceNumber = this.referenceNumber.trim();
                    }

                    return payload;
                },

                appendNote(value) {
                    if (! value) return;

                    const current = this.notes.trim();
                    if (current.split('، ').map(part => part.trim()).includes(value)) return;

                    this.notes = current ? `${current}، ${value}` : value;
                },

                resetOrder() {
                    this.cart = [];
                    this.customer = '';
                    this.customerPhone = '';
                    this.notes = '';
                    this.hasChange = false;
                    this.changeMethod = 'cash';
                    this.changeAccountId = '';
                    this.changeAmount = '';
                    this.accountId = '';
                    this.transferredAmount = '';
                    this.referenceNumber = '';
                    this.discount = '';
                    this.submitting = false;
                },

                async onSubmit(e) {
                    if (!this.canSubmit) { e.preventDefault(); return; }

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

                        this.queueFailed = true;
                        this.submitting = false;
                    }
                },
            };
        };
</script>
@endsection
