@extends('layouts.app')

@section('title', __('الحسابات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الحسابات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('حسابات البنوك والمحافظ، ولكل حساب دفتر حركات ورصيد.') }}</p>
            </div>
            <a href="{{ route('accounts.create') }}"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('حساب جديد') }}">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('حساب جديد') }}
            </a>
        </header>

        <form method="GET" action="{{ route('accounts.index') }}" class="reveal-up relative max-w-md" style="--d:60ms">
            <x-keep-per-page />
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث باسم صاحب الحساب...') }}" aria-label="{{ __('بحث') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($accounts->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">wallet</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد حسابات بعد') }}</p>
                    <a href="{{ route('accounts.create') }}" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('أنشئ أول حساب') }}">{{ __('أنشئ أول حساب') }}</a>
                </div>
            @else
                <x-table-responsive :headers="[__('صاحب الحساب'), __('نقدي/البنك/المحفظة'), __('العملة'), ['label' => __('الرصيد'), 'align' => 'end'], __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($accounts as $account)
                            <tr class="reveal-up cursor-pointer transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms"
                                onclick="window.location='{{ route('accounts.show', $account->id) }}'">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <span class="material-symbols-outlined !text-xl">{{ $account->kind->icon() }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <span class="block font-body-sm font-bold">{{ $account->holder_name }}</span>
                                            @unless ($account->active)<span class="text-[11px] font-bold text-error">{{ __('معطّل') }}</span>@endunless
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">
                                    {{ $account->institutionName() ?: '-' }}
                                    @if ($account->typeName())<span class="text-outline"> · {{ $account->typeName() }}</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant"><span dir="ltr">{{ $account->currency?->symbol }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold {{ (float) $account->balance < 0 ? 'text-error' : 'text-on-surface' }}">
                                    <x-money :amount="$account->balance" :symbol="$account->currency?->symbol ?? ''" />
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('accounts.show', $account->id) }}" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('عرض') }}" title="{{ __('عرض') }}">
                                        <span class="material-symbols-outlined !text-xl rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($accounts as $account)
                            <a href="{{ route('accounts.show', $account->id) }}" class="reveal-up tonal-elevation block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined !text-xl">{{ $account->kind->icon() }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-body-sm font-bold">{{ $account->holder_name }}</p>
                                        <p class="truncate text-[11px] text-on-surface-variant">{{ $account->institutionName() ?: '-' }}@if ($account->typeName()) · {{ $account->typeName() }}@endif</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2 border-t border-outline-variant/50 pt-3">
                                    <span class="text-[11px] text-on-surface-variant" dir="ltr">{{ $account->currency?->symbol }}</span>
                                    <span class="font-bold {{ (float) $account->balance < 0 ? 'text-error' : 'text-on-surface' }}">
                                        <x-money :amount="$account->balance" :symbol="$account->currency?->symbol ?? ''" />
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$accounts" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
