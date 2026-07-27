{{--
    Icon chooser for a catalogue entry or category.

    A grid of the whitelisted glyphs grouped by trade, because the catalogue is
    not assumed to be food any more - a fruit shop looks under "بقالة وفاكهة",
    a fuel station under "محروقات وسيارات".

    Binds to an Alpine property (`model`) rather than owning state, so it works
    inside the existing add/edit modals: opening the edit dialog sets that
    property and the selection follows. The hidden input is what actually
    submits, so the whitelist on the server stays the real gate - this is an
    affordance, not the validation.

    Collapsed by default: most entries never need an icon, and six groups open at
    once would bury the rest of the form.
--}}
@props([
    'model',                 // Alpine expression holding the chosen ligature
    'label' => null,
])

@php $icons = app(\App\Services\Tenant\CatalogIcons::class); @endphp

<div class="space-y-1.5" x-data="{ pickerOpen: false }">
    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">
        {{ $label ?? __('الأيقونة') }} <span class="text-outline">({{ __('اختياري') }})</span>
    </label>

    <input type="hidden" name="icon" :value="{{ $model }} || ''">

    <button type="button" @click="pickerOpen = !pickerOpen"
        class="flex w-full items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-start transition-colors hover:bg-surface-container-low"
        :aria-expanded="pickerOpen ? 'true' : 'false'">
        <span class="flex items-center gap-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <span class="material-symbols-outlined !text-xl" x-text="{{ $model }} || 'category'">category</span>
            </span>
            <span class="font-body-sm text-on-surface-variant"
                x-text="{{ $model }} ? {{ $model }} : @js(__('بدون أيقونة'))">{{ __('بدون أيقونة') }}</span>
        </span>
        <span class="material-symbols-outlined !text-xl text-outline transition-transform" :class="pickerOpen && 'rotate-180'">expand_more</span>
    </button>

    <div x-show="pickerOpen" x-cloak x-collapse
        class="max-h-64 space-y-4 overflow-y-auto rounded-xl border border-outline-variant bg-surface-container-low p-3">

        {{-- Clearing is a first-class choice, not an absence of one. --}}
        <button type="button" @click="{{ $model }} = ''; pickerOpen = false"
            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 font-body-sm text-body-sm text-on-surface-variant transition-colors hover:bg-surface-container-high"
            :class="!{{ $model }} && 'bg-surface-container-high font-bold text-primary'">
            <span class="material-symbols-outlined !text-lg">block</span>{{ __('بدون أيقونة') }}
        </button>

        @foreach ($icons->groups() as $group => $names)
            <div>
                <p class="mb-1.5 font-label-caps text-[10px] uppercase tracking-wider text-outline">{{ __($group) }}</p>
                <div class="grid grid-cols-6 gap-1.5">
                    @foreach ($names as $name)
                        <button type="button" @click="{{ $model }} = @js($name); pickerOpen = false"
                            class="flex aspect-square items-center justify-center rounded-lg border border-transparent text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                            :class="{{ $model }} === @js($name) && '!border-primary bg-primary/10 text-primary'"
                            title="{{ $name }}" aria-label="{{ $name }}">
                            <span class="material-symbols-outlined !text-xl">{{ $name }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
