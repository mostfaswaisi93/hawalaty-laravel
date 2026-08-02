@extends('layouts.app')

@section('title', __('الثوابت - حوالاتي'))

@section('content')
    @php

        $currentLicense = app()->bound('currentLicense') ? app('currentLicense') : null;
        $canUseCustomLists = (bool) $currentLicense?->hasFeature(\App\Enums\PackageFeature::Constants);
    @endphp
    <div class="page-enter space-y-6" x-data="{ listOpen: {{ $errors->has('title') ? 'true' : 'false' }}, submitting: false, confirmOpen: false, confirmMessage: '', confirmFormId: null }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الثوابت') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('قوائم قيم جاهزة تُعيد استخدامها في عملك.') }}</p>
            </div>
            @if ($canUseCustomLists)
                <button type="button" @click="listOpen = true; submitting = false"
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('قائمة جديدة') }}">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('قائمة جديدة') }}
                </button>
            @endif
        </header>

        <div x-data="{
                open: {{ $errors->hasAny(['catalogLabel', 'categoryLabel', 'itemLabel', 'catalogIcon']) ? 'true' : 'false' }},
                saving: false,
                catalogLabel: @js(old('catalogLabel', $naming->raw(\App\Services\Tenant\CatalogNaming::CATALOG) ?? '')),
                categoryLabel: @js(old('categoryLabel', $naming->raw(\App\Services\Tenant\CatalogNaming::CATEGORY) ?? '')),
                itemLabel: @js(old('itemLabel', $naming->raw(\App\Services\Tenant\CatalogNaming::ITEM) ?? '')),
                catalogIcon: @js(old('catalogIcon', $naming->isConfigured() ? $naming->icon() : '')),
            }"
            @keydown.escape.window="open = false">

            <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl {{ $naming->isConfigured() ? 'bg-primary/10 text-primary' : 'bg-secondary-container/20 text-secondary' }}">
                        <span class="material-symbols-outlined !text-2xl">{{ $naming->isConfigured() ? $naming->icon() : 'add_circle' }}</span>
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-headline-md text-headline-md text-on-surface">
                            {{ $naming->isConfigured() ? $naming->catalog() : __('لم تُنشئ قائمتك بعد') }}
                        </h2>
                        <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                            @if ($naming->isConfigured())
                                {{ __(':category · :item', ['category' => $naming->category(), 'item' => $naming->item()]) }}
                            @else
                                {{ __('اختر لها اسماً وأيقونة - عندها فقط تظهر في القائمة الجانبية.') }}
                            @endif
                        </p>
                    </div>
                </div>

                <button type="button" @click="open = true"
                    class="press flex flex-shrink-0 items-center gap-2 rounded-xl px-5 py-2.5 font-body-sm font-bold shadow-lg transition-all {{ $naming->isConfigured() ? 'border border-outline-variant bg-surface-container-lowest text-on-surface hover:bg-surface-container' : 'bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container' }}"
                    title="{{ $naming->isConfigured() ? __('تعديل القائمة') : __('إنشاء القائمة') }}">
                    <span class="material-symbols-outlined !text-xl">{{ $naming->isConfigured() ? 'edit' : 'add' }}</span>
                    {{ $naming->isConfigured() ? __('تعديل القائمة') : __('إنشاء القائمة') }}
                </button>
            </div>

            </section>

            <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="catalog-dialog-title">
                <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>

                <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                    <x-modal-close on-close="open = false" />

                    <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                        <h3 id="catalog-dialog-title" class="font-headline-md text-headline-md">{{ __('قائمة الأصناف') }}</h3>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __('سمِّ ما تبيعه بلغة عملك واختر أيقونته.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('constants.naming.update') }}" @submit="saving = true" class="space-y-5 p-6">
                        @csrf
                        @method('PUT')

                        @php $businessType = app(\App\Services\Tenant\BusinessProfile::class)->type(); @endphp
                        <div class="space-y-2">
                            <span class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تصنيف نشاطك') }}</span>
                            <div class="flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                                <span class="material-symbols-outlined !text-2xl flex-shrink-0 text-primary">{{ $businessType->icon() }}</span>
                                <div class="min-w-0">
                                    <p class="font-body-lg font-bold text-on-surface">{{ $businessType->label() }}</p>
                                    <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ __('يُضبط من إدارة المنصّة، وهو الذي يحدّد الوحدات المتاحة لك. تواصل مع الدعم لتغييره.') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="catalogLabel" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم القائمة') }}</label>
                            <input id="catalogLabel" name="catalogLabel" maxlength="40" x-model="catalogLabel"
                                placeholder="{{ __('مثال: قائمة الطعام، المحروقات، الكتب') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            @error('catalogLabel')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5" x-data="{ custom: {{ in_array(old('categoryLabel', $naming->raw(\App\Services\Tenant\CatalogNaming::CATEGORY)), array_merge([null, ''], $naming->categoryPresets()), true) ? 'false' : 'true' }} }">
                                <label for="categoryLabel" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مسمّى الفئة') }}</label>
                                <select id="categoryLabel" x-show="!custom" @change="$event.target.value === '__other' ? (custom = true, categoryLabel = '') : categoryLabel = $event.target.value"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                    <option value="">{{ __('الافتراضي') }} ({{ $naming->defaults()[\App\Services\Tenant\CatalogNaming::CATEGORY] }})</option>
                                    @foreach ($naming->categoryPresets() as $option)
                                        <option value="{{ $option }}" @selected(old('categoryLabel', $naming->raw(\App\Services\Tenant\CatalogNaming::CATEGORY)) === $option)>{{ $option }}</option>
                                    @endforeach
                                    <option value="__other">{{ __('أخرى...') }}</option>
                                </select>
                                <input x-show="custom" x-cloak type="text" maxlength="24" x-model="categoryLabel"
                                    aria-label="{{ __('مسمّى الفئة') }}"
                                    placeholder="{{ __('اكتب المسمّى') }}"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                <input type="hidden" name="categoryLabel" :value="categoryLabel">
                                @error('categoryLabel')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="space-y-1.5" x-data="{ custom: {{ in_array(old('itemLabel', $naming->raw(\App\Services\Tenant\CatalogNaming::ITEM)), array_merge([null, ''], $naming->itemPresets()), true) ? 'false' : 'true' }} }">
                                <label for="itemLabel" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مسمّى الصنف') }}</label>
                                <select id="itemLabel" x-show="!custom" @change="$event.target.value === '__other' ? (custom = true, itemLabel = '') : itemLabel = $event.target.value"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                    <option value="">{{ __('الافتراضي') }} ({{ $naming->defaults()[\App\Services\Tenant\CatalogNaming::ITEM] }})</option>
                                    @foreach ($naming->itemPresets() as $option)
                                        <option value="{{ $option }}" @selected(old('itemLabel', $naming->raw(\App\Services\Tenant\CatalogNaming::ITEM)) === $option)>{{ $option }}</option>
                                    @endforeach
                                    <option value="__other">{{ __('أخرى...') }}</option>
                                </select>
                                <input x-show="custom" x-cloak type="text" maxlength="24" x-model="itemLabel"
                                    aria-label="{{ __('مسمّى الصنف') }}"
                                    placeholder="{{ __('اكتب المسمّى') }}"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                <input type="hidden" name="itemLabel" :value="itemLabel">
                                @error('itemLabel')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div>
                            <x-icon-picker model="catalogIcon" name="catalogIcon" :label="__('أيقونة القائمة')" />
                            @error('catalogIcon')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="-mx-6 -mb-6 mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                            <p class="font-body-sm text-body-sm text-outline">{{ __('أفرغ الاسم لإزالة القائمة من القائمة الجانبية.') }}</p>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="open = false"
                                    class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container"
                                    title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                                <button type="submit" :disabled="saving"
                                    class="press flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container disabled:cursor-not-allowed disabled:opacity-50"
                                    title="{{ __('حفظ') }}">
                                    <span class="material-symbols-outlined !text-xl" :class="saving && 'animate-spin'"
                                        x-text="saving ? 'progress_activity' : 'save'">save</span>
                                    <span x-text="saving ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if (! $canUseCustomLists)
            <div class="reveal-scale tonal-elevation flex flex-col items-center gap-3 rounded-3xl border border-dashed border-outline-variant bg-surface-container-lowest py-16 text-center">
                <span class="material-symbols-outlined !text-5xl text-outline/60">lock</span>
                <p class="font-body-lg text-on-surface-variant">{{ __('القوائم المخصّصة غير متاحة في باقتك الحالية.') }}</p>
            </div>
        @elseif ($lists->isEmpty())
            <div class="reveal-scale tonal-elevation flex flex-col items-center gap-3 rounded-3xl border border-dashed border-outline-variant bg-surface-container-lowest py-20 text-center">
                <span class="material-symbols-outlined !text-5xl text-outline/60">tune</span>
                <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد قوائم بعد') }}</p>
                <button type="button" @click="listOpen = true; submitting = false" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('أنشئ أول قائمة') }}">{{ __('أنشئ أول قائمة') }}</button>
            </div>
        @else
            <div class="stagger grid gap-5 md:grid-cols-2">
                @foreach ($lists as $list)
                    <section class="tonal-elevation flex flex-col rounded-3xl border border-outline-variant/50 bg-surface-container-lowest">
                        <div class="flex items-center justify-between gap-3 border-b border-outline-variant/50 p-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <span class="material-symbols-outlined">list</span>
                                </div>
                                <div>
                                    <h2 class="font-headline-md text-headline-md text-on-surface">{{ $list->title }}</h2>
                                    <p class="font-body-sm text-outline">{{ $list->items->count() }} {{ __('عنصر') }}</p>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('constants.destroy', $list->id) }}" id="delete-list-{{ $list->id }}" class="inline-flex">
                                @csrf @method('DELETE')
                            </form>
                            <button type="button" aria-label="{{ __('حذف القائمة') }}" title="{{ __('حذف القائمة') }}"
                                @click="confirmOpen = true; confirmMessage = '{{ __('حذف القائمة وكل عناصرها؟') }}'; confirmFormId = 'delete-list-{{ $list->id }}'"
                                class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                <span class="material-symbols-outlined !text-xl">delete</span>
                            </button>
                        </div>

                        <div class="flex-1 p-5">
                            @if ($list->items->isEmpty())
                                <p class="py-4 text-center font-body-sm text-outline">{{ __('لا عناصر بعد') }}</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($list->items as $item)
                                        <span class="group inline-flex items-center gap-1.5 rounded-full bg-surface-container-high py-1.5 pe-2 ps-3 font-body-sm text-on-surface">
                                            {{ $item->value }}
                                            <form method="POST" action="{{ route('constants.items.destroy', [$list->id, $item->id]) }}" class="inline-flex"
                                                x-data="{ removing: false }" @submit="removing = true">
                                                @csrf @method('DELETE')
                                                <button type="submit" :disabled="removing" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}" class="flex h-5 w-5 items-center justify-center rounded-full text-outline transition-colors hover:bg-error/15 hover:text-error disabled:cursor-not-allowed disabled:opacity-60"
                                                    @mouseenter="showTipBelow($el, @js(__('حذف')))" @mouseleave="hideTip()" @focus="showTipBelow($el, @js(__('حذف')))" @blur="hideTip()">
                                                    <span class="material-symbols-outlined !text-base" :class="removing && 'animate-spin'"
                                                        x-text="removing ? 'progress_activity' : 'close'">close</span>
                                                </button>
                                            </form>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('constants.items.store', $list->id) }}" class="flex items-center gap-2 border-t border-outline-variant/50 p-4"
                            x-data="{ adding: false }" @submit="adding = true">
                            @csrf
                            <input name="value" required placeholder="{{ __('أضِف عنصراً...') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-sm outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            <button type="submit" :disabled="adding" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary text-on-primary transition-colors hover:bg-primary-container hover:text-on-primary-container disabled:cursor-not-allowed disabled:opacity-60" aria-label="{{ __('إضافة عنصر') }}" title="{{ __('إضافة عنصر') }}"
                                @mouseenter="showTipBelow($el, @js(__('إضافة عنصر')))" @mouseleave="hideTip()" @focus="showTipBelow($el, @js(__('إضافة عنصر')))" @blur="hideTip()">
                                <span class="material-symbols-outlined" :class="adding && 'animate-spin'"
                                    x-text="adding ? 'progress_activity' : 'add'">add</span>
                            </button>
                        </form>
                    </section>
                @endforeach
            </div>
        @endif

        <div x-show="listOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="listOpen" x-transition.opacity></div>
            <div x-show="listOpen" @keydown.escape.window="listOpen = false"
                x-init="$watch('listOpen', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="list-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="listOpen = false" />
                <h3 id="list-modal-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md">{{ __('قائمة جديدة') }}</h3>
                <form method="POST" action="{{ route('constants.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="constants-title" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('عنوان القائمة') }}</label>
                        <input id="constants-title" name="title" value="{{ old('title') }}" required placeholder="{{ __('مثال: طرق التوصيل') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="listOpen = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('إضافة') }}'"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
