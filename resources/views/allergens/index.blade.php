@extends('layouts.app')

@section('title', __('مسبّبات الحساسية - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('مسبّبات الحساسية') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('الأربعة عشر مسبّبًا الواجب الإفصاح عنها. أعلنها على المكوّن، والطبق يرثها من وصفته.') }}</p>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أصناف لم تُراجَع') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $unreviewedCount > 0 ? 'text-error' : '' }}">{{ $unreviewedCount }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('لا أحد فحصها بعد') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أطباق على مكوّنات غير مفحوصة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $blindDishes->isNotEmpty() ? 'text-error' : '' }}">{{ $blindDishes->count() }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('بطاقتها تبدو مطمئنة بلا أساس') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المسبّبات المُعلَنة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold">{{ $spread->count() }} / 14</p>
                <p class="mt-1 font-body-sm text-outline" dir="ltr">EU 1169/2011</p>
            </x-card>
        </div>

        @if ($blindDishes->isNotEmpty())
            <div class="reveal-up rounded-2xl border border-error/30 bg-error-container/10 p-4" style="--d:100ms">
                <div class="flex items-start gap-3 text-error">
                    <span class="material-symbols-outlined flex-shrink-0">dangerous</span>
                    <div class="min-w-0">
                        <p class="font-body-sm font-bold">{{ __('أطباق تُعلن «بلا مسبّبات» اعتمادًا على مكوّنات لم يفحصها أحد') }}</p>
                        <p class="mt-1 font-body-sm">{{ __('افحص مكوّناتها قبل الاعتماد على بطاقتها.') }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($blindDishes->take(12) as $dish)
                        <a href="{{ route('allergens.edit', $dish->id) }}"
                            class="rounded-lg bg-error/10 px-2.5 py-1.5 font-body-sm text-error transition-colors hover:bg-error/20">{{ $dish->name }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('allergens.index') }}" class="reveal-up flex flex-col gap-3 sm:flex-row" style="--d:140ms">
            <x-keep-per-page />
            <div class="relative flex-1">
                <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالاسم...') }}" aria-label="{{ __('بحث') }}"
                    class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
            </div>
            <select name="filter" onchange="this.form.submit()" aria-label="{{ __('تصفية') }}"
                class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                <option value="">{{ __('كل الأصناف') }}</option>
                <option value="unreviewed" @selected(($filter ?? '') === 'unreviewed')>{{ __('لم تُراجَع فقط') }}</option>
            </select>
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($items->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">allergies</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد أصناف') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[$naming->item(), __('البطاقة'), __('المراجعة'), __('')]">
                    <x-slot:table>
                        @foreach ($items as $item)
                            @php $label = $labels[$item->id]; @endphp
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold">{{ $item->name }}</p>
                                    <p class="font-body-sm text-outline">{{ $item->menuCategory?->name }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($label->isEmpty())
                                        <span class="font-body-sm text-on-surface-variant">{{ $item->allergensReviewed() ? __('لا يحتوي أيًّا منها') : '—' }}</span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($label as $row)
                                                <span class="inline-flex items-center gap-1 rounded-lg px-2 py-1 font-body-sm
                                                    {{ $row['presence'] === \App\Enums\AllergenPresence::Contains
                                                        ? 'bg-error/10 text-error'
                                                        : 'bg-secondary-container/40 text-secondary' }}"
                                                    title="{{ $row['presence']->label() }}{{ $row['inherited'] ? ' — '.__('موروث من الوصفة') : '' }}">
                                                    <span class="material-symbols-outlined !text-sm">{{ $row['allergen']->icon() }}</span>
                                                    {{ $row['allergen']->label() }}
                                                    @if ($row['inherited'])
                                                        <span class="material-symbols-outlined !text-xs opacity-60">link</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($item->allergensReviewed())
                                        <span class="font-body-sm text-on-surface-variant">{{ $item->allergens_reviewed_at->translatedFormat('j M Y') }}</span>
                                    @else
                                        <span class="rounded-lg bg-error/10 px-2 py-1 font-body-sm font-bold text-error">{{ __('لم تُراجَع') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('allergens.edit', $item->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('إفصاح مسبّبات :item', ['item' => $item->name]) }}">
                                        <span class="material-symbols-outlined !text-xl">edit</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($items as $item)
                            @php $label = $labels[$item->id]; @endphp
                            <a href="{{ route('allergens.edit', $item->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-body-lg font-bold">{{ $item->name }}</p>
                                    @unless ($item->allergensReviewed())
                                        <span class="whitespace-nowrap rounded-lg bg-error/10 px-2 py-1 font-body-sm font-bold text-error">{{ __('لم تُراجَع') }}</span>
                                    @endunless
                                </div>
                                @if ($label->isNotEmpty())
                                    <p class="mt-2 font-body-sm text-on-surface-variant">{{ $label->pluck('allergen')->map->label()->implode('، ') }}</p>
                                @endif
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$items" :perPage="$perPage" />
            @endif
        </section>
    </div>
@endsection
