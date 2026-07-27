@extends('layouts.app')

@section('title', __('العملاء - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('العملاء') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('تعرّف على عملائك الدائمين وسجل طلباتهم.') }}</p>
        </header>

        <form method="GET" action="{{ route('customers.index') }}" class="reveal-up relative max-w-md" style="--d:60ms">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالاسم أو الهاتف...') }}" aria-label="{{ __('بحث') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($customers->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">group</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا يوجد عملاء بعد') }}</p>
                    <p class="font-body-sm text-outline">{{ __('يُضاف العميل تلقائياً عند أول طلب يحمل رقم جواله.') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('العميل'), ['label' => __('عدد الطلبات'), 'align' => 'end'], ['label' => __('إجمالي الإنفاق'), 'align' => 'end'], __('آخر طلب')]">
                    <x-slot:table>
                        @foreach ($customers as $c)
                            <tr class="reveal-up cursor-pointer transition-colors hover:bg-surface-container-low/50 focus:bg-surface-container-low/50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary"
                                style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms"
                                tabindex="0" role="link" aria-label="{{ $c->name }}"
                                onclick="location.href='{{ route('customers.show', $c->id) }}'"
                                @keydown.enter="location.href='{{ route('customers.show', $c->id) }}'">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-white">
                                            @if (filled($c->name))
                                                {{ mb_substr($c->name, 0, 1) }}
                                            @else
                                                <span class="material-symbols-outlined !text-xl">person</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-body-sm font-bold text-on-surface">{{ $c->name ?: __('بدون اسم') }}</p>
                                            {{-- The number IS the customer, so it sits with the name rather
                                                 than in a column of its own. --}}
                                            <p class="truncate text-[11px] text-on-surface-variant"><span dir="ltr">{{ $c->phone ?: '-' }}</span></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-body-sm text-on-surface-variant"><span dir="ltr">{{ number_format($c->order_count) }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold text-primary"><span dir="ltr">{{ number_format($c->total_spent ?? 0, 2) }} ₪</span></td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->diffForHumans() : '-' }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($customers as $c)
                            <a href="{{ route('customers.show', $c->id) }}" class="reveal-up tonal-elevation block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4"
                                style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-center gap-3">
                                    <div class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">{{ mb_substr($c->name, 0, 1) }}</div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-body-sm font-bold">{{ $c->name }}</p>
                                        <p class="truncate text-[11px] text-on-surface-variant" dir="ltr">{{ $c->phone ?: '-' }}</p>
                                    </div>
                                    <p class="whitespace-nowrap font-bold text-primary" dir="ltr">{{ number_format($c->total_spent ?? 0, 2) }} ₪</p>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between gap-2 ps-[52px] text-[11px] text-on-surface-variant">
                                    <span>{{ __('عدد الطلبات') }}: {{ $c->order_count }}</span>
                                    <span>{{ $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->diffForHumans() : '-' }}</span>
                                </div>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$customers" :perPage="$perPage" />

                @if ($customers->hasPages())
                    <div class="mt-6 flex items-center justify-between gap-4 border-t border-outline-variant/50 pt-5">
                        <span class="font-body-sm text-on-surface-variant">
                            {{ __('صفحة') }} {{ $customers->currentPage() }} {{ __('من') }} {{ $customers->lastPage() }}
                        </span>
                        <div class="flex items-center gap-2">
                            @if ($customers->onFirstPage())
                                <span class="flex items-center gap-1 rounded-xl border border-outline-variant/60 px-4 py-2 font-body-sm text-outline/60">
                                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span> {{ __('السابق') }}
                                </span>
                            @else
                                <a href="{{ $customers->previousPageUrl() }}" class="flex items-center gap-1 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">
                                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span> {{ __('السابق') }}
                                </a>
                            @endif

                            @if ($customers->hasMorePages())
                                <a href="{{ $customers->nextPageUrl() }}" class="flex items-center gap-1 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">
                                    {{ __('التالي') }} <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                                </a>
                            @else
                                <span class="flex items-center gap-1 rounded-xl border border-outline-variant/60 px-4 py-2 font-body-sm text-outline/60">
                                    {{ __('التالي') }} <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
