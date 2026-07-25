@extends('layouts.admin')

@section('title', __('الإعدادات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: 'add',
            submitting: false,
            form: { key: '{{ old('key', '') }}', value: '{{ old('value', '') }}' },
            openAdd() { this.mode = 'add'; this.submitting = false; this.form = { key: '', value: '' }; this.open = true; },
            openEdit(setting) { this.mode = 'edit'; this.submitting = false; this.form = { ...setting }; this.open = true; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الإعدادات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('إعدادات المنصّة العامة (مفتاح / قيمة).') }}</p>
            </div>
            <button type="button" @click="openAdd()"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('إضافة إعداد') }}">
                <span class="material-symbols-outlined !text-xl">add</span>{{ __('إضافة إعداد') }}
            </button>
        </header>

        <section class="tonal-elevation reveal-up rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 80ms">
            @if ($settings->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center" style="--d: 240ms">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">settings</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد إعدادات بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('المفتاح'), __('القيمة'), __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($settings as $setting)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3"><code class="font-numeral-lg text-body-sm" dir="ltr">{{ $setting->key }}</code></td>
                                <td class="px-4 py-3 font-body-sm text-on-surface-variant">{{ $setting->value ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <button type="button" @click="openEdit(@js(['key' => $setting->key, 'value' => $setting->value]))" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}"><span class="material-symbols-outlined !text-xl">edit</span></button>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($settings as $setting)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <code class="text-body-sm font-bold" dir="ltr">{{ $setting->key }}</code>
                                    <button type="button" @click="openEdit(@js(['key' => $setting->key, 'value' => $setting->value]))" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}"><span class="material-symbols-outlined !text-lg">edit</span></button>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $setting->value ?? '—' }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$settings" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false" role="dialog" aria-modal="true"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <h3 class="border-b border-outline-variant/60 px-6 py-4 font-headline-md text-headline-md" x-text="mode === 'edit' ? '{{ __('تعديل الإعداد') }}' : '{{ __('إضافة إعداد') }}'"></h3>
                <form method="POST" action="{{ route('admin.settings.upsert') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المفتاح') }}</label>
                        <input name="key" x-model="form.key" :readonly="mode === 'edit'" required dir="ltr" placeholder="site_name" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary read-only:opacity-60" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('القيمة') }}</label>
                        <textarea name="value" x-model="form.value" rows="3" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting" class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50" x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('حفظ') }}'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
