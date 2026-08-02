@extends('layouts.app')

@section('title', __('الضمان والصيانة - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, submitting: false, serialId: '' }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الضمان والصيانة') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('من يدفع ثمن الإصلاح يُحسم من تاريخ الوحدة، لا من تقدير اللحظة.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('warranty.lookup') }}"
                    class="flex items-center gap-2 rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined !text-xl">qr_code_scanner</span>
                    {{ __('بحث برقم تسلسلي') }}
                </a>
                <button type="button" @click="open = true"
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('مطالبة جديدة') }}
                </button>
            </div>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مفتوحة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $summary['open'] > 0 ? 'text-secondary' : '' }}">{{ $summary['open'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قيد الإصلاح') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $summary['inRepair'] }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تكلفة تحمّلها المحل') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($summary['absorbed'], 2) }} ₪</p>
            </x-card>
        </div>

        @if ($offenders->isNotEmpty())
            <x-card title="{{ __('أكثر الأصناف مطالبات') }}" subtitle="{{ __('مؤشّر على مورد أو خط منتجات فيه مشكلة.') }}" class="reveal-up" style="--d:100ms">
                <div class="flex flex-wrap gap-2">
                    @foreach ($offenders as $row)
                        <span class="inline-flex items-center gap-2 rounded-xl bg-surface-container px-3 py-2 font-body-sm">
                            {{ $row['item']->name }}
                            <span class="font-numeral-lg font-bold text-error">{{ $row['claims'] }}</span>
                        </span>
                    @endforeach
                </div>
            </x-card>
        @endif

        <form method="GET" action="{{ route('warranty.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:140ms">
            <x-keep-per-page />
            <div class="relative flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالرقم أو اسم العميل...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <select name="status" onchange="this.form.submit()" aria-label="{{ __('الحالة') }}"
                class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="open" @selected(($status ?? '') === 'open')>{{ __('غير منتهية') }}</option>
                @foreach ($statuses as $case)
                    <option value="{{ $case->value }}" @selected(($status ?? '') === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($claims->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">verified_user</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مطالبات') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    __('العميل'),
                    $naming->item(),
                    __('العطل'),
                    __('الغطاء'),
                    __('الحالة'),
                    __(''),
                ]">
                    <x-slot:table>
                        @foreach ($claims as $claim)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold">{{ $claim->customer_name }}</p>
                                    <p class="font-body-sm text-outline" dir="ltr">{{ $claim->customer_phone }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-body-sm">{{ $claim->item?->name ?? '—' }}</p>
                                    @if ($claim->serialNumber)
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $claim->serialNumber->serial }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant">{{ Str::limit($claim->issue, 60) }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $claim->under_warranty ? 'bg-success/10 text-success' : 'bg-surface-container text-on-surface-variant' }}">
                                        {{ $claim->under_warranty ? __('تحت الضمان') : __('خارج الضمان') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm font-bold {{ $claim->status->tone() }}">{{ $claim->status->label() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('warranty.show', $claim->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('فتح المطالبة') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($claims as $claim)
                            <a href="{{ route('warranty.show', $claim->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $claim->customer_name }}</p>
                                        <p class="font-body-sm text-outline">{{ $claim->item?->name }}</p>
                                    </div>
                                    <span class="whitespace-nowrap rounded-lg px-2 py-1 font-body-sm font-bold {{ $claim->status->tone() }}">{{ $claim->status->label() }}</span>
                                </div>
                                <p class="mt-2 font-body-sm text-on-surface-variant">{{ Str::limit($claim->issue, 80) }}</p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$claims" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="claim-title">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="open" @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="open = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="claim-title" class="font-headline-md text-headline-md">{{ __('مطالبة ضمان جديدة') }}</h3>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('للوحدات المسجَّلة تسلسليًا، ابحث بالرقم أولًا ليُحسم الغطاء تلقائيًا.') }}</p>
                </div>

                <form method="POST" action="{{ route('warranty.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="claim-item" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الصنف') }}</label>
                        <select id="claim-item" name="itemId" required
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('اختر') }}</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" @selected(old('itemId') === $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('itemId')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="claim-customer" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم العميل') }}</label>
                        <input id="claim-customer" name="customerName" value="{{ old('customerName') }}" required maxlength="255"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('customerName')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="claim-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهاتف') }}</label>
                        <input id="claim-phone" name="customerPhone" value="{{ old('customerPhone') }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="claim-issue" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('العطل') }}</label>
                        <textarea id="claim-issue" name="issue" rows="3" required maxlength="2000"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">{{ old('issue') }}</textarea>
                        @error('issue')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="underWarranty" value="0" />
                        <input id="claim-covered" type="checkbox" name="underWarranty" value="1" @checked(old('underWarranty'))
                            class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                        <label for="claim-covered" class="font-body-sm">{{ __('تحت الضمان') }}</label>
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('فتح المطالبة'))">{{ __('فتح المطالبة') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
