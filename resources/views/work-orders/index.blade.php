@extends('layouts.app')

@section('title', __('أوامر الشغل - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('أوامر الشغل') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('قطع وساعات عمل في فاتورة واحدة — وتُفوتَر من نفس الكاشير حتى لا يتحرّك المخزون بطريقتين.') }}</p>
            </div>
            <button type="button" @click="open = true"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('أمر شغل جديد') }}
            </button>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('على الأرض') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $summary['open'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بانتظار موافقة العميل') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $summary['awaitingApproval'] > 0 ? 'text-secondary' : '' }}">{{ $summary['awaitingApproval'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('جاهز للتسليم') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $summary['ready'] > 0 ? 'text-success' : '' }}">{{ $summary['ready'] }}</p>
            </x-card>
        </div>

        <form method="GET" action="{{ route('work-orders.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:100ms">
            <x-keep-per-page />
            <div class="relative flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالرقم أو العميل أو رقم اللوحة...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <select name="status" onchange="this.form.submit()" aria-label="{{ __('الحالة') }}"
                class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="open" @selected(($status ?? '') === 'open')>{{ __('على الأرض') }}</option>
                @foreach ($statuses as $case)
                    <option value="{{ $case->value }}" @selected(($status ?? '') === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($orders->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">build</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أوامر شغل') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    __('العميل'),
                    __('الموضوع'),
                    __('الفني'),
                    ['label' => __('القطع'), 'align' => 'end'],
                    ['label' => __('العمل'), 'align' => 'end'],
                    ['label' => __('الإجمالي'), 'align' => 'end'],
                    __('الحالة'),
                    __(''),
                ]">
                    <x-slot:table>
                        @foreach ($orders as $order)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold">{{ $order->customer_name }}</p>
                                    <p class="font-body-sm text-outline" dir="ltr">{{ $order->reference ?: mb_substr($order->id, -8) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-body-sm">{{ $order->subject ?: '—' }}</p>
                                    @if ($order->registration)
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $order->registration }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $order->resource?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $order->parts_total, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $order->labour_total, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $order->total, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $order->status->tone() }}">{{ $order->status->label() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('work-orders.show', $order->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('فتح أمر الشغل') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($orders as $order)
                            <a href="{{ route('work-orders.show', $order->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $order->customer_name }}</p>
                                        <p class="font-body-sm text-outline">{{ $order->subject ?: $order->registration }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $order->status->tone() }}">{{ $order->status->label() }}</span>
                                </div>
                                <p class="mt-2 font-numeral-lg font-bold" dir="ltr">{{ number_format((float) $order->total, 2) }} ₪</p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$orders" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="wo-title">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="open" @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="open = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="wo-title" class="font-headline-md text-headline-md">{{ __('أمر شغل جديد') }}</h3>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('سجّل ما وصل والشكوى؛ القطع وساعات العمل تُضاف بعد الفحص.') }}</p>
                </div>

                <form method="POST" action="{{ route('work-orders.store') }}" class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="wo-customer" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم العميل') }}</label>
                        <input id="wo-customer" name="customerName" value="{{ old('customerName') }}" required maxlength="255"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('customerName')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="wo-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهاتف') }}</label>
                        <input id="wo-phone" name="customerPhone" value="{{ old('customerPhone') }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="wo-subject" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الموضوع') }}</label>
                        <input id="wo-subject" name="subject" value="{{ old('subject') }}" maxlength="255" placeholder="{{ __('مثال: تويوتا كورولا 2016') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="wo-registration" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم اللوحة') }}</label>
                        <input id="wo-registration" name="registration" value="{{ old('registration') }}" maxlength="32" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="wo-odometer" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قراءة العدّاد') }}</label>
                        <input id="wo-odometer" type="number" min="0" name="odometer" value="{{ old('odometer') }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="wo-resource" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الفني') }}</label>
                        <select id="wo-resource" name="resourceId"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('بدون تحديد') }}</option>
                            @foreach ($resources as $resource)
                                <option value="{{ $resource->id }}" @selected(old('resourceId') === $resource->id)>{{ $resource->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="wo-complaint" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('شكوى العميل') }}</label>
                        <textarea id="wo-complaint" name="complaint" rows="3" maxlength="2000"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">{{ old('complaint') }}</textarea>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="wo-warranty" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('ضمان العمل (يوم)') }}</label>
                        <input id="wo-warranty" type="number" min="0" max="3650" name="workmanshipWarrantyDays" value="{{ old('workmanshipWarrantyDays', 0) }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('ضمان المحل على شغله — غير ضمان المصنّع على القطع.') }}</p>
                    </div>

                    <div class="-mx-6 -mb-6 mt-2 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4 sm:col-span-2">
                        <button type="button" @click="open = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الفتح...')) : @js(__('افتح أمر الشغل'))">{{ __('افتح أمر الشغل') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
