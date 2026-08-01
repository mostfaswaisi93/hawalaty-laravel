@props([
    'model',                 // Alpine expression holding the chosen ligature
    'label' => null,
    // The field this submits as. Defaults to the catalogue's own `icon` column;
    // the naming form on /constants posts it as `catalogIcon` instead, because
    // there it is the SECTION's glyph rather than one entry's.
    'name' => 'icon',
])

@php
    $icons = app(\App\Services\Tenant\CatalogIcons::class);

    $pickerId = 'icon-picker-'.\Illuminate\Support\Str::slug(str_replace('.', '-', $model));
@endphp

<div class="space-y-1.5" x-data="{ pickerOpen: false }">
    <label id="{{ $pickerId }}-label" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">
        {{ $label ?? __('الأيقونة') }} <span class="text-outline">({{ __('اختياري') }})</span>
    </label>

    <input type="hidden" name="{{ $name }}" :value="{{ $model }} || ''">

    <button type="button" @click="pickerOpen = !pickerOpen"
        aria-labelledby="{{ $pickerId }}-label {{ $pickerId }}-value"
        class="flex w-full items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-start transition-colors hover:bg-surface-container-low"
        :aria-expanded="pickerOpen ? 'true' : 'false'">
        <span class="flex items-center gap-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <span class="material-symbols-outlined !text-xl" x-text="{{ $model }} || 'category'">category</span>
            </span>
            <span id="{{ $pickerId }}-value" class="font-body-sm text-on-surface-variant"
                x-text="{{ $model }} ? {{ $model }} : @js(__('بدون أيقونة'))">{{ __('بدون أيقونة') }}</span>
        </span>
        <span class="material-symbols-outlined !text-xl text-outline transition-transform" :class="pickerOpen && 'rotate-180'">expand_more</span>
    </button>

    <div x-show="pickerOpen" x-cloak x-collapse
        class="max-h-72 space-y-4 overflow-y-auto rounded-xl border border-outline-variant bg-surface-container-low p-3">

        <button type="button" @click="{{ $model }} = ''; pickerOpen = false"
            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 font-body-sm text-body-sm text-on-surface-variant transition-colors hover:bg-surface-container-high"
            :class="!{{ $model }} && 'bg-surface-container-high font-bold text-primary'">
            <span class="material-symbols-outlined !text-lg">block</span>{{ __('بدون أيقونة') }}
        </button>

        @foreach ($icons->groups() as $group => $names)
            <div>
                <p class="mb-2 font-label-caps text-[10px] uppercase tracking-wider text-outline">{{ __($group) }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($names as $name)
                        <button type="button" @click="{{ $model }} = @js($name); pickerOpen = false"
                            class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl border border-outline-variant/60 bg-surface-container-lowest text-on-surface-variant shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:bg-primary/10 hover:text-primary hover:shadow-md"
                            :class="{{ $model }} === @js($name) && '!border-primary !bg-primary !text-on-primary shadow-md'"
                            title="{{ $name }}" aria-label="{{ $name }}">
                            <span class="material-symbols-outlined !text-2xl">{{ $name }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
