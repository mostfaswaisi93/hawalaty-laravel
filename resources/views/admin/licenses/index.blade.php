@extends('layouts.admin')

@section('title', __('التراخيص - حوالاتي'))

@php
    $statusMap = [
        'trial' => ['label' => __('تجريبي'), 'class' => 'bg-secondary/10 text-secondary'],
        'active' => ['label' => __('مُفعّل'), 'class' => 'bg-primary/10 text-primary'],
        'suspended' => ['label' => __('مُعلّق'), 'class' => 'bg-error/10 text-error'],
        'expired' => ['label' => __('منتهي'), 'class' => 'bg-outline/10 text-outline'],
    ];
@endphp

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('التراخيص') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('أنشئ التراخيص وأدِر حالتها وباقاتها.') }}</p>
            </div>
            <button type="button" @click="open = true; submitting = false"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('ترخيص جديد') }}">
                <span class="material-symbols-outlined !text-xl">add</span>{{ __('ترخيص جديد') }}
            </button>
        </header>

        <form method="GET" action="{{ route('admin.licenses.index') }}" class="reveal-up grid grid-cols-1 gap-3 md:grid-cols-4" style="--d: 80ms"
            x-data="{ filtering: false }" @submit="filtering = true">
            <x-keep-per-page />
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالاسم أو الرمز...') }}"
                aria-label="{{ __('ابحث بالاسم أو الرمز...') }}"
                class="rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary md:col-span-2" />
            <select name="status" aria-label="{{ __('الحالة') }}" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach ($statusMap as $key => $meta)
                    <option value="{{ $key }}" @selected(($status ?? '') === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="packageId" aria-label="{{ __('الباقة') }}" class="flex-1 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('كل الباقات') }}</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected(($packageId ?? '') === $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
                <button type="submit" :disabled="filtering" class="flex items-center justify-center rounded-xl bg-surface-container px-4 text-on-surface transition-colors hover:bg-surface-container-high disabled:cursor-not-allowed disabled:opacity-60" aria-label="{{ __('تصفية') }}" title="{{ __('تصفية') }}"
                    @mouseenter="showTipBelow($el, @js(__('تصفية')))" @mouseleave="hideTip()" @focus="showTipBelow($el, @js(__('تصفية')))" @blur="hideTip()">
                    <span class="material-symbols-outlined" :class="filtering && 'animate-spin'"
                        x-text="filtering ? 'progress_activity' : 'filter_alt'">filter_alt</span>
                </button>
            </div>
        </form>

        <section class="tonal-elevation reveal-up rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 160ms">
            @if ($licenses->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center" style="--d: 300ms">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">key_off</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد تراخيص') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('العميل'), __('الرمز'), __('الباقة'), __('الحالة'), __('ينتهي في'), __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($licenses as $license)
                            @php $meta = $statusMap[$license->status->value] ?? ['label' => $license->status->value, 'class' => 'bg-outline/10 text-outline']; @endphp
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm font-bold">{{ $license->customer_name }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><code class="rounded bg-surface-container px-2 py-1 font-numeral-lg text-body-sm" dir="ltr">{{ $license->license_code }}</code></td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $license->package?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $meta['class'] }}">{{ $meta['label'] }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $license->expired_at?->translatedFormat('j F Y') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('admin.licenses.show', $license->id) }}" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('عرض') }}">
                                            <span class="material-symbols-outlined !text-xl">visibility</span>
                                        </a>
                                        @if (in_array($license->status->value, ['active', 'trial']))
                                            <form method="POST" action="{{ route('admin.licenses.suspend', $license->id) }}" id="suspend-{{ $license->id }}">@csrf</form>
                                            <button type="button" aria-label="{{ __('تعليق') }}" title="{{ __('تعليق') }}"
                                                @click="confirmOpen = true; confirmMessage = '{{ __('تعليق هذا الترخيص؟') }}'; confirmFormId = 'suspend-{{ $license->id }}'; confirmTitle = '{{ __('تأكيد التعليق') }}'; confirmLabel = '{{ __('تعليق') }}'"
                                                class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                                <span class="material-symbols-outlined !text-xl">pause_circle</span>
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route('admin.licenses.activate', $license->id) }}" class="inline-flex"
                                                x-data="{ activating: false }" @submit="activating = true">@csrf
                                                <button type="submit" :disabled="activating" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary disabled:cursor-not-allowed disabled:opacity-60" aria-label="{{ __('تفعيل') }}" title="{{ __('تفعيل') }}"
                                                    @mouseenter="showTipBelow($el, @js(__('تفعيل')))" @mouseleave="hideTip()" @focus="showTipBelow($el, @js(__('تفعيل')))" @blur="hideTip()">
                                                    <span class="material-symbols-outlined !text-xl" :class="activating && 'animate-spin'"
                                                        x-text="activating ? 'progress_activity' : 'play_circle'">play_circle</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($licenses as $license)
                            @php $meta = $statusMap[$license->status->value] ?? ['label' => $license->status->value, 'class' => 'bg-outline/10 text-outline']; @endphp
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-body-sm font-bold">{{ $license->customer_name }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant"><code dir="ltr">{{ $license->license_code }}</code> · {{ $license->package?->name ?? '-' }}</p>
                                <div class="mt-3 flex items-center justify-end gap-1 border-t border-outline-variant/50 pt-3">
                                    <a href="{{ route('admin.licenses.show', $license->id) }}" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('عرض') }}">
                                        <span class="material-symbols-outlined !text-lg">visibility</span>
                                    </a>
                                    @if (in_array($license->status->value, ['active', 'trial']))
                                        <form method="POST" action="{{ route('admin.licenses.suspend', $license->id) }}" id="suspend-card-{{ $license->id }}">@csrf</form>
                                        <button type="button" aria-label="{{ __('تعليق') }}" title="{{ __('تعليق') }}"
                                            @click="confirmOpen = true; confirmMessage = '{{ __('تعليق هذا الترخيص؟') }}'; confirmFormId = 'suspend-card-{{ $license->id }}'; confirmTitle = '{{ __('تأكيد التعليق') }}'; confirmLabel = '{{ __('تعليق') }}'"
                                            class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error">
                                            <span class="material-symbols-outlined !text-lg">pause_circle</span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('admin.licenses.activate', $license->id) }}" class="inline-flex"
                                            x-data="{ activating: false }" @submit="activating = true">@csrf
                                            <button type="submit" :disabled="activating" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-primary/10 hover:text-primary disabled:cursor-not-allowed disabled:opacity-60" aria-label="{{ __('تفعيل') }}" title="{{ __('تفعيل') }}"
                                                @mouseenter="showTipBelow($el, @js(__('تفعيل')))" @mouseleave="hideTip()" @focus="showTipBelow($el, @js(__('تفعيل')))" @blur="hideTip()">
                                                <span class="material-symbols-outlined !text-lg" :class="activating && 'animate-spin'"
                                                    x-text="activating ? 'progress_activity' : 'play_circle'">play_circle</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$licenses" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false" role="dialog" aria-modal="true"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="open = false" />
                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 class="font-headline-md text-headline-md">{{ __('إنشاء ترخيص جديد') }}</h3>
                    <p class="mt-1 font-body-sm text-on-surface-variant">{{ __('أنشئ قاعدة بيانات للعميل في cPanel أولاً، ثم أدخل بياناتها هنا لربطها وترحيل جداولها.') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.licenses.store') }}" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="admin-licenses-business-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم النشاط التجاري') }}</label>
                        <input id="admin-licenses-business-name" name="businessName" value="{{ old('businessName') }}" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label for="admin-licenses-owner-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم المالك') }}</label>
                        <input id="admin-licenses-owner-name" name="ownerName" value="{{ old('ownerName') }}" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label for="admin-licenses-owner-email" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بريد المالك') }}</label>
                        <input id="admin-licenses-owner-email" type="email" name="ownerEmail" value="{{ old('ownerEmail') }}" required dir="ltr" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label for="admin-licenses-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهاتف (اختياري)') }}</label>
                        <input id="admin-licenses-phone" name="phone" value="{{ old('phone') }}" dir="ltr" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="admin-licenses-address" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('العنوان بالتفصيل') }}</label>
                        <input id="admin-licenses-address" name="address" value="{{ old('address') }}" placeholder="{{ __('غزة - شارع النصر - شمال مركز رشاد الشوا') }}" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-body-sm text-outline">{{ __('يُطبع على فواتير هذا المحل مع رقم الهاتف.') }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label for="admin-licenses-package-id" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الباقة') }}</label>
                        <select id="admin-licenses-package-id" name="packageId" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('الأرخص افتراضياً') }}</option>
                            @foreach ($packages as $package)
                                <option value="{{ $package->id }}" @selected(old('packageId') === $package->id)>{{ $package->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <fieldset class="space-y-2 md:col-span-2" x-data="{ vertical: @js(old('businessType', \App\Enums\BusinessType::default()->value)) }">
                        <legend class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تصنيف النشاط') }}</legend>
                        <p class="font-body-sm text-outline">{{ __('يحدّد هذا الاختيار الوحدات والشاشات التي تُفتح داخل النسخة. لا يستطيع العميل تغييره.') }}</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($businessTypes as $type)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 transition"
                                    :class="vertical === @js($type->value)
                                        ? 'border-primary bg-primary text-on-primary'
                                        : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'">
                                    <input type="radio" name="businessType" value="{{ $type->value }}" x-model="vertical" class="sr-only" required />
                                    <span class="material-symbols-outlined !text-xl flex-shrink-0">{{ $type->icon() }}</span>
                                    <span class="font-body-sm leading-tight">{{ $type->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @foreach ($businessTypes as $type)
                            <div x-show="vertical === @js($type->value)" x-cloak class="rounded-xl bg-surface-container-low p-3">
                                <p class="font-body-sm text-on-surface-variant">{{ $type->description() }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($type->capabilities() as $capability)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-secondary-container px-2 py-1 font-label-sm text-label-sm text-on-secondary-container">
                                            <span class="material-symbols-outlined !text-sm">{{ $capability->icon() }}</span>{{ $capability->label() }}
                                        </span>
                                    @endforeach
                                </div>
                                <p class="mt-2 font-body-sm text-outline" dir="ltr">{{ implode(' · ', $type->standards()) }}</p>
                            </div>
                        @endforeach
                        @error('businessType')
                            <p class="font-body-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="space-y-1.5 md:col-span-2">
                        <label for="admin-licenses-password" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('كلمة مرور المالك') }}</label>
                        <input id="admin-licenses-password" type="password" name="password" required dir="ltr" placeholder="{{ __('8 أحرف على الأقل، حروف وأرقام') }}" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="md:col-span-2">
                        <div class="mt-2 flex items-center gap-2 border-t border-outline-variant/60 pt-4">
                            <span class="material-symbols-outlined !text-xl text-primary">database</span>
                            <h4 class="font-body-lg font-bold text-on-surface">{{ __('قاعدة بيانات العميل (cPanel)') }}</h4>
                        </div>
                        <p class="mt-1 font-body-sm text-on-surface-variant">{{ __('أنشئ القاعدة والمستخدم وامنحه كامل الصلاحيات عليها في cPanel، ثم أدخل البيانات. سيتم اختبار الاتصال وترحيل الجداول تلقائياً.') }}</p>
                    </div>
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="admin-licenses-db-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم قاعدة البيانات') }}</label>
                        <input id="admin-licenses-db-name" name="dbName" value="{{ old('dbName') }}" required dir="ltr" placeholder="cpaneluser_hawalaty" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label for="admin-licenses-db-username" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مستخدم قاعدة البيانات') }}</label>
                        <input id="admin-licenses-db-username" name="dbUsername" value="{{ old('dbUsername') }}" required dir="ltr" placeholder="cpaneluser_dbuser" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label for="admin-licenses-db-password" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('كلمة مرور قاعدة البيانات') }}</label>
                        <input id="admin-licenses-db-password" type="password" name="dbPassword" required dir="ltr" autocomplete="new-password" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label for="admin-licenses-db-host" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المضيف (اختياري)') }}</label>
                        <input id="admin-licenses-db-host" name="dbHost" value="{{ old('dbHost') }}" dir="ltr" placeholder="127.0.0.1" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label for="admin-licenses-db-port" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المنفذ (اختياري)') }}</label>
                        <input id="admin-licenses-db-port" type="number" name="dbPort" value="{{ old('dbPort') }}" dir="ltr" placeholder="3306" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4 md:col-span-2">
                        <button type="button" @click="open = false" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting" class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50" x-text="submitting ? '{{ __('جارٍ الإنشاء...') }}' : '{{ __('إنشاء') }}'"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
