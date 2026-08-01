@extends('layouts.app')

@section('title', __('الكتالوجات - حوالاتي'))

@php

    $catalogJs = [];
    foreach ($catalogs as $key => $c) {
        $catalogJs[$key] = [
            'singular' => $c['singular'],
            'symbol' => $c['symbol'],
            'currencies' => $c['currencies'],
            'placeholder' => $c['placeholder'],
            'storeUrl' => url('catalogs/'.$key),
        ];
    }
@endphp

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            tab: '{{ array_key_first($catalogs) }}',
            meta: @js($catalogJs),
            open: false, mode: 'add', submitting: false,
            form: { id: '', name: '', symbol: '', active: true, currencies: [] },
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
            init() {
                const failed = @js(old('catalog'));
                const hash = (window.location.hash || '').replace('#', '');
                const start = failed || hash;
                if (this.meta[start]) this.tab = start;
                @if ($errors->any())
                    if (failed && this.meta[failed]) {
                        this.mode = @js(old('id')) ? 'edit' : 'add';
                        this.form = { id: @js(old('id', '')), name: @js(old('name', '')), symbol: @js(old('symbol', '')), active: {{ old('active', '1') ? 'true' : 'false' }}, currencies: @js(old('currencies', [])) };
                        this.open = true;
                    }
                @endif
            },
            current() { return this.meta[this.tab] || {}; },
            openAdd() { this.mode = 'add'; this.submitting = false; this.form = { id: '', name: '', symbol: '', active: true, currencies: [] }; this.open = true; },
            openEdit(item) { this.mode = 'edit'; this.submitting = false; this.form = { id: item.id, name: item.name, symbol: item.symbol || '', active: item.active, currencies: item.currencyIds || [] }; this.open = true; },
            actionUrl() { const c = this.current(); return this.mode === 'edit' ? c.storeUrl + '/' + this.form.id : c.storeUrl; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الكتالوجات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('القوائم المرجعية التي تُبنى منها الحسابات: بنوك ومحافظ وعملات وأنواع وصناديق.') }}</p>
            </div>
            <button type="button" @click="openAdd()"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95"
                :title="'{{ __('إضافة') }} ' + (current().singular || '')">
                <span class="material-symbols-outlined !text-xl">add</span>
                <span x-text="'{{ __('إضافة') }} ' + (current().singular || '')"></span>
            </button>
        </header>

        <div class="reveal-up flex gap-1 overflow-x-auto rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-1.5" style="--d:60ms" role="tablist">
            @foreach ($catalogs as $key => $catalog)
                <button type="button" role="tab" @click="tab = '{{ $key }}'"
                    :aria-selected="tab === '{{ $key }}' ? 'true' : 'false'"
                    class="flex flex-shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 font-body-sm font-bold transition-colors"
                    :class="tab === '{{ $key }}' ? 'bg-primary text-on-primary shadow' : 'text-on-surface-variant hover:bg-surface-container-high'">
                    <span class="material-symbols-outlined !text-xl">{{ $catalog['icon'] }}</span>
                    <span class="whitespace-nowrap">{{ $catalog['plural'] }}</span>
                    <span class="rounded-full px-2 py-0.5 font-numeral-lg text-[11px] font-bold"
                        :class="tab === '{{ $key }}' ? 'bg-on-primary/20 text-on-primary' : 'bg-surface-container-high text-on-surface-variant'">{{ $catalog['items']->count() }}</span>
                </button>
            @endforeach
        </div>

        @foreach ($catalogs as $key => $catalog)
            <section x-show="tab === '{{ $key }}'" x-cloak id="{{ $key }}"
                class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="mb-5 flex items-start gap-3">
                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined">{{ $catalog['icon'] }}</span>
                    </span>
                    <div>
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ $catalog['plural'] }}</h2>
                        <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $catalog['hint'] }}</p>
                    </div>
                </div>

                @if ($catalog['items']->isEmpty())
                    <div class="reveal-scale flex flex-col items-center gap-3 py-12 text-center">
                        <span class="material-symbols-outlined !text-5xl text-outline/50">{{ $catalog['icon'] }}</span>
                        <p class="font-body-lg text-on-surface-variant">{{ __('لا عناصر بعد') }}</p>
                        <button type="button" @click="openAdd()" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('إضافة') }} {{ $catalog['singular'] }}">{{ __('إضافة') }} {{ $catalog['singular'] }}</button>
                    </div>
                @else
                    <x-table-responsive :headers="array_values(array_filter([
                        __('الاسم'),
                        $catalog['symbol'] ? __('الرمز') : null,
                        $catalog['currencies'] ? __('العملات المدعومة') : null,
                        __('الحالة'),
                        __('إجراءات'),
                    ]))">
                        <x-slot:table>
                            @foreach ($catalog['items'] as $item)
                                @php $editPayload = ['id' => $item->id, 'name' => $item->name, 'symbol' => $catalog['symbol'] ? $item->symbol : '', 'active' => (bool) $item->active, 'currencyIds' => $catalog['currencies'] ? $item->currencies->pluck('id')->all() : []]; @endphp
                                <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="font-body-sm font-bold">{{ $item->name }}</span>
                                    </td>
                                    @if ($catalog['symbol'])
                                        <td class="whitespace-nowrap px-4 py-3 font-numeral-lg text-body-lg text-on-surface-variant"><span dir="ltr">{{ $item->symbol }}</span></td>
                                    @endif
                                    @if ($catalog['currencies'])
                                        <td class="px-4 py-3">@include('catalogs._currencies', ['item' => $item])</td>
                                    @endif
                                    <td class="px-4 py-3">
                                        @include('catalogs._toggle', ['catalog' => $key, 'item' => $item])
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1">
                                            <button type="button" @click="openEdit(@js($editPayload))"
                                                class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                                <span class="material-symbols-outlined !text-xl">edit</span>
                                            </button>
                                            @include('catalogs._delete', ['catalog' => $key, 'item' => $item])
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot:table>
                        <x-slot:cards>
                            @foreach ($catalog['items'] as $item)
                                @php $editPayload = ['id' => $item->id, 'name' => $item->name, 'symbol' => $catalog['symbol'] ? $item->symbol : '', 'active' => (bool) $item->active, 'currencyIds' => $catalog['currencies'] ? $item->currencies->pluck('id')->all() : []]; @endphp
                                <div class="reveal-up tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-body-sm font-bold">{{ $item->name }}</p>
                                            @if ($catalog['symbol'])
                                                <p class="font-numeral-lg text-[13px] text-on-surface-variant" dir="ltr">{{ $item->symbol }}</p>
                                            @endif
                                        </div>
                                        @include('catalogs._toggle', ['catalog' => $key, 'item' => $item])
                                    </div>
                                    @if ($catalog['currencies'])
                                        <div class="mt-3">@include('catalogs._currencies', ['item' => $item])</div>
                                    @endif
                                    <div class="mt-3 flex items-center justify-end gap-1 border-t border-outline-variant/50 pt-3">
                                        <button type="button" @click="openEdit(@js($editPayload))"
                                            class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                            <span class="material-symbols-outlined !text-lg">edit</span>
                                        </button>
                                        @include('catalogs._delete', ['catalog' => $key, 'item' => $item])
                                    </div>
                                </div>
                            @endforeach
                        </x-slot:cards>
                    </x-table-responsive>
                @endif
            </section>
        @endforeach

        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>
            <div x-show="open" @keydown.escape.window="open = false"
                x-init="$watch('open', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="catalog-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="open = false" />

                <h3 id="catalog-modal-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md"
                    x-text="(mode === 'edit' ? '{{ __('تعديل') }} ' : '{{ __('إضافة') }} ') + (current().singular || '')"></h3>

                <form :action="actionUrl()" method="POST" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="catalog" :value="tab">
                    <input type="hidden" name="id" :value="form.id">
                    <input type="hidden" name="active" :value="form.active ? 1 : 0">

                    <div class="space-y-1.5">
                        <label for="catalog-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input id="catalog-name" name="name" x-model="form.name" required :placeholder="current().placeholder || ''"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('name')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5" x-show="current().symbol" x-cloak>
                        <label for="catalog-symbol" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الرمز') }}</label>
                        <input id="catalog-symbol" name="symbol" x-model="form.symbol" :required="!!current().symbol" dir="ltr" maxlength="8" placeholder="₪"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('symbol')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5" x-show="current().currencies" x-cloak>
                        <span class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('العملات المدعومة') }}</span>
                        @if ($currencyOptions->isEmpty())
                            <p class="flex items-start gap-1.5 rounded-xl border border-dashed border-outline-variant bg-surface-container-low px-4 py-3 font-body-sm text-body-sm text-on-surface-variant">
                                <span class="material-symbols-outlined !text-base">info</span>
                                {{ __('أضِف عملة أولاً من تبويب العملات.') }}
                            </p>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach ($currencyOptions as $cur)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 font-body-sm transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:checked]:text-primary">
                                        <input type="checkbox" name="currencies[]" value="{{ $cur->id }}" x-model="form.currencies" class="h-4 w-4 rounded accent-primary" />
                                        <span class="font-bold">{{ $cur->name }}</span>
                                        <span class="font-numeral-lg text-on-surface-variant" dir="ltr">{{ $cur->symbol }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('currencies')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                        <span class="font-body-sm font-bold text-on-surface">{{ __('نشط') }}</span>
                        <input type="checkbox" x-model="form.active" class="h-5 w-5 rounded accent-primary" />
                    </label>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : (mode === 'edit' ? '{{ __('حفظ التغييرات') }}' : '{{ __('إضافة') }}')"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
