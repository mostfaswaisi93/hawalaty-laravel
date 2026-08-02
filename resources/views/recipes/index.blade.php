@extends('layouts.app')

@section('title', __('الوصفات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ open: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الوصفات والمكوّنات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('ما يتكوّن منه كل صنف — ومنه تُحسب التكلفة الحقيقية ويُخصم المخزون.') }}</p>
            </div>
            @if ($candidates->isNotEmpty())
                <button type="button" @click="open = true"
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('وصفة جديدة') }}
                </button>
            @endif
        </header>

        <form method="GET" action="{{ route('recipes.index') }}" class="reveal-up relative max-w-md" style="--d:60ms">
            <x-keep-per-page />
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالاسم...') }}" aria-label="{{ __('بحث') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($items->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">blender</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد وصفات بعد') }}</p>
                    <p class="font-body-sm text-outline">{{ __('بدون وصفة، بيع الطبق لا يخصم شيئًا من المخزون.') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[
                    $naming->item(),
                    ['label' => __('عدد المكوّنات'), 'align' => 'end'],
                    ['label' => __('تكلفة نظرية'), 'align' => 'end'],
                    ['label' => __('سعر البيع'), 'align' => 'end'],
                    ['label' => __('الهامش'), 'align' => 'end'],
                    __('نمط الخصم'),
                    __(''),
                ]">
                    <x-slot:table>
                        @foreach ($items as $item)
                            @php
                                $figures = $costs[$item->id];
                                $margin = $figures['margin'];
                            @endphp
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <p class="font-body-sm font-bold">{{ $item->name }}</p>
                                    <p class="font-body-sm text-outline">{{ $item->menuCategory?->name }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg">{{ $item->recipeComponents->count() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($figures['cost'], 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format((float) $item->price, 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $margin !== null && $margin < 0 ? 'text-error' : '' }}" dir="ltr">
                                    {{ $margin === null ? '—' : $margin.'%' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-lg px-2 py-1 font-body-sm {{ $item->assemblesToOrder() ? 'bg-secondary-container/40 text-secondary' : 'bg-surface-container text-on-surface-variant' }}">
                                        {{ $item->assemblesToOrder() ? __('يُجهَّز عند الطلب') : __('يُنتَج للمخزون') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('recipes.edit', $item->id) }}"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                        aria-label="{{ __('تعديل وصفة :item', ['item' => $item->name]) }}">
                                        <span class="material-symbols-outlined !text-xl">edit</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($items as $item)
                            @php $figures = $costs[$item->id]; @endphp
                            <a href="{{ route('recipes.edit', $item->id) }}" class="reveal-up block rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <p class="font-body-lg font-bold">{{ $item->name }}</p>
                                <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">
                                    {{ __('تكلفة نظرية') }}: {{ number_format($figures['cost'], 3) }} · {{ $item->recipeComponents->count() }} {{ __('مكوّن') }}
                                </p>
                            </a>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$items" :perPage="$perPage" />
            @endif
        </section>

        @if ($candidates->isNotEmpty())
            <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="new-recipe-title">
                <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>
                <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                    x-show="open" @keydown.escape.window="open = false"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                    <x-modal-close on-close="open = false" />

                    <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                        <h3 id="new-recipe-title" class="font-headline-md text-headline-md">{{ __('وصفة جديدة') }}</h3>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('اختر الصنف الذي تريد تحديد مكوّناته.') }}</p>
                    </div>

                    <div class="max-h-96 overflow-y-auto p-6">
                        <ul class="space-y-1">
                            @foreach ($candidates as $candidate)
                                <li>
                                    <a href="{{ route('recipes.edit', $candidate->id) }}"
                                        class="flex items-center justify-between gap-3 rounded-xl px-4 py-3 font-body-lg transition-colors hover:bg-surface-container">
                                        {{ $candidate->name }}
                                        <span class="material-symbols-outlined !text-xl text-outline rtl:-scale-x-100">chevron_right</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
