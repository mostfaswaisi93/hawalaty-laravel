@extends('layouts.app')

@section('title', $item->name.' - '.__('مسبّبات الحساسية'))

@php $inherited = $label->where('inherited', true); @endphp

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            submitting: false,
            chosen: {{ \Illuminate\Support\Js::from($declared->map->value) }},
            has(key) { return this.chosen[key] !== undefined },
            toggle(key) {
                if (this.has(key)) { delete this.chosen[key]; }
                else { this.chosen[key] = 'contains'; }
            },
            setPresence(key, value) { if (this.has(key)) this.chosen[key] = value },
        }">

        <header>
            <a href="{{ route('allergens.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('مسبّبات الحساسية') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $item->name }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">
                {{ $item->hasRecipe()
                    ? __('أعلن ما يضيفه هذا الصنف بنفسه فقط — ما تحمله مكوّناته يُورَّث تلقائيًا.')
                    : __('أعلن ما يحتويه هذا الصنف. كل طبق يستخدمه سيرث إعلانك.') }}
            </p>
        </header>

        @unless ($item->allergensReviewed())
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4 text-error" style="--d:60ms">
                <span class="material-symbols-outlined flex-shrink-0">error</span>
                <div>
                    <p class="font-body-sm font-bold">{{ __('هذا الصنف لم يُفحَص بعد') }}</p>
                    <p class="mt-1 font-body-sm">{{ __('الحفظ يسجّل أنه فُحص — حتى لو لم تختر أي مسبّب، فذلك إجابة لا فراغ.') }}</p>
                </div>
            </div>
        @endunless

        @if ($inherited->isNotEmpty())
            <x-card title="{{ __('موروث من الوصفة') }}" subtitle="{{ __('يأتي من المكوّنات ولا يُعدَّل هنا — عدّله على المكوّن نفسه.') }}" class="reveal-up" style="--d:100ms">
                <div class="flex flex-wrap gap-2">
                    @foreach ($inherited as $row)
                        <span class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 font-body-sm
                            {{ $row['presence'] === \App\Enums\AllergenPresence::Contains
                                ? 'bg-error/10 text-error'
                                : 'bg-secondary-container/40 text-secondary' }}">
                            <span class="material-symbols-outlined !text-lg">{{ $row['allergen']->icon() }}</span>
                            {{ $row['allergen']->label() }}
                            <span class="opacity-70">· {{ $row['presence']->shortLabel() }}</span>
                        </span>
                    @endforeach
                </div>
            </x-card>
        @endif

        <form method="POST" action="{{ route('allergens.update', $item->id) }}" @submit="submitting = true">
            @csrf
            @method('PUT')

            <x-card title="{{ __('إفصاح هذا الصنف') }}" subtitle="{{ __('«يحتوي» إعلان عن مكوّن مقصود، و«قد يحتوي» تحذير من تلوّث عرضي — لا تخلط بينهما.') }}">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($allergens as $allergen)
                        <div class="rounded-2xl border p-3 transition"
                            :class="has(@js($allergen->value)) ? 'border-primary bg-primary/5' : 'border-outline-variant'">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" :checked="has(@js($allergen->value))" @change="toggle(@js($allergen->value))"
                                    class="mt-1 h-5 w-5 flex-shrink-0 rounded border-outline-variant text-primary focus:ring-primary" />
                                <span class="min-w-0">
                                    <span class="flex items-center gap-1.5 font-body-lg font-bold text-on-surface">
                                        <span class="material-symbols-outlined !text-lg">{{ $allergen->icon() }}</span>
                                        {{ $allergen->label() }}
                                    </span>
                                    <span class="mt-0.5 block font-body-sm text-outline">{{ $allergen->note() }}</span>
                                </span>
                            </label>

                            <div x-show="has(@js($allergen->value))" x-cloak class="mt-3 flex gap-2 ps-8">
                                @foreach (\App\Enums\AllergenPresence::cases() as $presence)
                                    <label class="flex flex-1 cursor-pointer items-center justify-center rounded-lg border px-2 py-1.5 transition"
                                        :class="chosen[@js($allergen->value)] === @js($presence->value)
                                            ? 'border-primary bg-primary text-on-primary'
                                            : 'border-outline-variant bg-surface-container-lowest text-on-surface-variant'">
                                        <input type="radio" :name="'declarations[' + @js($allergen->value) + ']'" value="{{ $presence->value }}"
                                            :checked="chosen[@js($allergen->value)] === @js($presence->value)"
                                            @change="setPresence(@js($allergen->value), @js($presence->value))" class="sr-only" />
                                        <span class="font-body-sm">{{ $presence->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-outline-variant/50 pt-5">
                    <a href="{{ route('allergens.index') }}" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</a>
                    <button type="submit" :disabled="submitting"
                        class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                        x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ الإفصاح'))">{{ __('حفظ الإفصاح') }}</button>
                </div>
            </x-card>
        </form>
    </div>
@endsection
