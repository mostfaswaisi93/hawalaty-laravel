@extends('layouts.admin')

@section('title', __('الإعدادات - حوالاتي'))

@section('content')
    {{--
        A grouped form over App\Settings\SettingRegistry, not a key/value table.
        Each field's label, help text, input type and validation come from the
        registry, so adding a setting there makes it appear here with no change
        to this file.

        Anything in the database the registry does not know about is listed at
        the bottom under "custom keys" rather than hidden, so a key added by a
        previous admin (or through the API) is never lost.
    --}}
    <div class="page-enter space-y-6"
        x-data="{
            submitting: false,
            customOpen: {{ $errors->has('key') ? 'true' : 'false' }},
            customMode: 'add',
            customForm: { key: '{{ old('key', '') }}', value: '{{ old('value', '') }}' },
            confirmOpen: false, confirmMessage: '', confirmFormId: null, confirmTitle: '', confirmLabel: '',
            openAddCustom() { this.customMode = 'add'; this.customForm = { key: '', value: '' }; this.customOpen = true; },
            openEditCustom(item) { this.customMode = 'edit'; this.customForm = { ...item }; this.customOpen = true; },
        }">

        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الإعدادات') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('إعدادات المنصّة التي تؤثر على التراخيص وصفحات العملاء.') }}</p>
        </header>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6" @submit="submitting = true">
            @csrf
            @method('PUT')

            @foreach ($groups as $group)
                <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: {{ 60 + $loop->index * 70 }}ms">
                    <div class="mb-6 flex items-center gap-3">
                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">{{ $group['icon'] }}</span>
                        </span>
                        <div>
                            <h2 class="font-headline-md text-headline-md text-on-surface">{{ __($group['label']) }}</h2>
                            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ __($group['description']) }}</p>
                        </div>
                    </div>

                    <div class="divide-y divide-outline-variant/50">
                        @foreach ($group['settings'] as $setting)
                            @php
                                $key = $setting['key'];
                                $field = "settings.{$key}";
                                $current = old("settings.{$key}", $values[$key] ?? $setting['default']);
                                $hasError = $errors->has($field);
                                // From the setting's OWN rules - see boundsFor().
                                $bounds = $registry->boundsFor($setting);
                            @endphp

                            <div class="grid grid-cols-1 gap-x-6 gap-y-2 py-5 first:pt-0 last:pb-0 md:grid-cols-3">
                                <div class="md:col-span-1">
                                    <label for="setting-{{ $key }}" class="block font-body-sm font-bold text-on-surface">{{ __($setting['label']) }}</label>
                                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ __($setting['description']) }}</p>
                                    <code class="mt-1.5 inline-block rounded bg-surface-container-high px-1.5 py-0.5 text-[11px] text-outline" dir="ltr">{{ $key }}</code>
                                </div>

                                <div class="md:col-span-2">
                                    @if ($setting['type'] === \App\Settings\SettingType::Boolean)
                                        <label class="flex cursor-pointer items-center gap-3">
                                            <input type="checkbox" id="setting-{{ $key }}" name="settings[{{ $key }}]" value="1"
                                                @checked($current)
                                                class="h-5 w-5 flex-shrink-0 rounded accent-primary" />
                                            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ __('مُفعّل') }}</span>
                                        </label>
                                    @elseif ($setting['type'] === \App\Settings\SettingType::Textarea)
                                        <textarea id="setting-{{ $key }}" name="settings[{{ $key }}]" rows="3"
                                            placeholder="{{ $setting['placeholder'] ?? '' }}"
                                            @class([
                                                'w-full rounded-xl border bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2',
                                                'border-error focus:ring-error' => $hasError,
                                                'border-outline-variant focus:ring-primary' => ! $hasError,
                                            ])>{{ $current }}</textarea>
                                    @else
                                        <div class="relative">
                                            <input id="setting-{{ $key }}"
                                                type="{{ $setting['type']->value }}"
                                                name="settings[{{ $key }}]"
                                                value="{{ $current }}"
                                                placeholder="{{ $setting['placeholder'] ?? '' }}"
                                                @if ($setting['type'] === \App\Settings\SettingType::Number)
                                                    inputmode="numeric"
                                                    @isset($bounds['min']) min="{{ $bounds['min'] }}" @endisset
                                                    @isset($bounds['max']) max="{{ $bounds['max'] }}" @endisset
                                                @endif
                                                @if ($setting['type'] !== \App\Settings\SettingType::Text) dir="ltr" @endif
                                                @class([
                                                    'w-full rounded-xl border bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2',
                                                    'pe-16' => isset($setting['unit']),
                                                    'border-error focus:ring-error' => $hasError,
                                                    'border-outline-variant focus:ring-primary' => ! $hasError,
                                                ]) />
                                            @isset($setting['unit'])
                                                <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center font-body-sm text-body-sm text-outline">{{ __($setting['unit']) }}</span>
                                            @endisset
                                        </div>
                                    @endif

                                    @error($field)
                                        <p class="mt-1.5 flex items-center gap-1 font-body-sm text-body-sm text-error">
                                            <span class="material-symbols-outlined !text-base">error</span>{{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- Sticky so Save stays reachable however many groups are added. --}}
            <div class="reveal-up sticky bottom-4 z-20 flex items-center justify-between gap-4 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest/90 p-4 shadow-xl backdrop-blur" style="--d: 240ms">
                <p class="hidden font-body-sm text-body-sm text-on-surface-variant sm:block">{{ __('لن تُحفظ التغييرات حتى تضغط حفظ.') }}</p>
                <button type="submit" :disabled="submitting"
                    class="press flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-body-sm font-bold text-on-primary shadow-lg shadow-primary/25 transition-all hover:bg-primary-container hover:text-on-primary-container hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50"
                    title="{{ __('حفظ الإعدادات') }}">
                    <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                        x-text="submitting ? 'progress_activity' : 'save'">save</span>
                    <span x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ الإعدادات'))">{{ __('حفظ الإعدادات') }}</span>
                </button>
            </div>
        </form>

        {{-- Custom keys --}}
        <section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 300ms">
            <div class="mb-5 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-secondary-container/20 text-secondary">
                        <span class="material-symbols-outlined">code</span>
                    </span>
                    <div>
                        <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('مفاتيح مخصّصة') }}</h2>
                        <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ __('مفاتيح مخزّنة خارج إعدادات المنصّة الأساسية.') }}</p>
                    </div>
                </div>
                <button type="button" @click="openAddCustom()"
                    class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container"
                    title="{{ __('إضافة مفتاح') }}">
                    <span class="material-symbols-outlined !text-xl">add</span>{{ __('إضافة مفتاح') }}
                </button>
            </div>

            @if (empty($custom))
                <div class="flex flex-col items-center gap-2 py-10 text-center">
                    <span class="material-symbols-outlined !text-4xl text-outline/50">code_off</span>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ __('لا توجد مفاتيح مخصّصة') }}</p>
                </div>
            @else
                <ul class="stagger divide-y divide-outline-variant/50">
                    @foreach ($custom as $key => $value)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <code class="text-body-sm font-bold text-on-surface" dir="ltr">{{ $key }}</code>
                                <p class="mt-0.5 truncate font-body-sm text-body-sm text-on-surface-variant">{{ $value ?: '-' }}</p>
                            </div>
                            <div class="flex flex-shrink-0 items-center gap-1">
                                <button type="button" @click="openEditCustom(@js(['key' => $key, 'value' => $value]))"
                                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                                    aria-label="{{ __('تعديل') }}" title="{{ __('تعديل') }}">
                                    <span class="material-symbols-outlined !text-xl">edit</span>
                                </button>
                                <form method="POST" action="{{ route('admin.settings.destroy', ['key' => $key]) }}" id="delete-setting-{{ $loop->index }}" class="hidden">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button"
                                    @click="confirmOpen = true;
                                            confirmTitle = @js(__('حذف المفتاح'));
                                            confirmMessage = @js(__('سيتم حذف هذا المفتاح وقيمته نهائياً.'));
                                            confirmLabel = @js(__('حذف'));
                                            confirmFormId = 'delete-setting-{{ $loop->index }}'"
                                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                    aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}">
                                    <span class="material-symbols-outlined !text-xl">delete</span>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Custom key modal --}}
        <div x-show="customOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="customOpen" x-transition.opacity></div>
            <div x-show="customOpen" @keydown.escape.window="customOpen = false" role="dialog" aria-modal="true" aria-labelledby="custom-setting-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="customOpen = false" />
                <h3 id="custom-setting-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md"
                    x-text="customMode === 'edit' ? @js(__('تعديل المفتاح')) : @js(__('إضافة مفتاح'))"></h3>
                {{-- Reuses the page-level `submitting`: this modal and the settings
                     form above are mutually exclusive, so they can never be in
                     flight at the same time. --}}
                <form method="POST" action="{{ route('admin.settings.upsert') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="custom-key" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المفتاح') }}</label>
                        <input id="custom-key" name="key" x-model="customForm.key" :readonly="customMode === 'edit'" required dir="ltr" placeholder="my_custom_key"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary read-only:opacity-60" />
                        @error('key')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="custom-value" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('القيمة') }}</label>
                        <textarea id="custom-value" name="value" x-model="customForm.value" rows="3"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="customOpen = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container"
                            title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))"
                            title="{{ __('حفظ') }}">{{ __('حفظ') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
