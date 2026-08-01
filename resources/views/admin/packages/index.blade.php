@extends('layouts.admin')

@section('title', __('الباقات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: 'add',
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
            form: { id: '', name: '{{ old('name', '') }}', price: '{{ old('price', '') }}', deviceLimit: '{{ old('deviceLimit', '') }}', features: '', featureKeys: @js(old('featureKeys', [])) },
            openAdd() { this.mode = 'add'; this.submitting = false; this.form = { id: '', name: '', price: '', deviceLimit: '', features: '', featureKeys: [] }; this.open = true; },
            openEdit(pkg) { this.mode = 'edit'; this.submitting = false; this.form = { featureKeys: [], ...pkg }; this.open = true; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الباقات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('عرّف باقات الاشتراك وأسعارها وحدود الأجهزة.') }}</p>
            </div>
            <button type="button" @click="openAdd()"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('إضافة باقة') }}">
                <span class="material-symbols-outlined !text-xl">add</span>{{ __('إضافة باقة') }}
            </button>
        </header>

        @if ($packages->isEmpty())
            <div class="tonal-elevation reveal-scale flex flex-col items-center gap-3 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest py-16 text-center" style="--d: 120ms">
                <span class="material-symbols-outlined !text-5xl text-outline/50">inventory_2</span>
                <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد باقات بعد') }}</p>
            </div>
        @else
            <div class="stagger grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($packages as $package)
                    @php
                        $allFeatures = $package->features ?? [];
                        $knownKeys = array_column(\App\Enums\PackageFeature::cases(), 'value');
                        $featureKeys = array_values(array_intersect($allFeatures, $knownKeys));
                        $featuresText = implode("\n", array_values(array_diff($allFeatures, $knownKeys)));
                    @endphp
                    <div class="tonal-elevation flex flex-col rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-headline-md text-headline-md font-bold">{{ $package->name }}</h3>
                                <p class="mt-1 font-numeral-lg text-numeral-lg font-bold text-primary"><span dir="ltr">{{ number_format($package->price, 2) }} ₪</span></p>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="openEdit(@js(['id' => $package->id, 'name' => $package->name, 'price' => (float) $package->price, 'deviceLimit' => $package->device_limit, 'features' => $featuresText, 'featureKeys' => $featureKeys]))"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                    <span class="material-symbols-outlined !text-xl">edit</span>
                                </button>
                                <form method="POST" action="{{ route('admin.packages.destroy', $package->id) }}" id="del-pkg-{{ $package->id }}">@csrf @method('DELETE')</form>
                                <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}"
                                    @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذه الباقة؟') }}'; confirmFormId = 'del-pkg-{{ $package->id }}'"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error">
                                    <span class="material-symbols-outlined !text-xl">delete</span>
                                </button>
                            </div>
                        </div>
                        <p class="mt-3 flex items-center gap-1.5 font-body-sm text-on-surface-variant">
                            <span class="material-symbols-outlined !text-lg">devices</span>{{ __('حد الأجهزة:') }} {{ $package->device_limit }}
                        </p>
                        @if (! empty($package->features))
                            <ul class="mt-4 space-y-2 border-t border-outline-variant/50 pt-4">
                                @foreach ($package->features as $feature)
                                    <li class="flex items-center gap-2 font-body-sm">
                                        <span class="material-symbols-outlined !text-lg text-primary">check_circle</span>{{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>

            <x-table-footer :paginator="$packages" :perPage="$perPage" />
        @endif

        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-labelledby="package-modal-title"
                x-init="$watch('open', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="open = false" />
                <h3 id="package-modal-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md" x-text="mode === 'edit' ? @js(__('تعديل الباقة')) : @js(__('إضافة باقة'))"></h3>
                <form :action="mode === 'edit' ? '{{ url('admin/packages') }}/' + form.id : '{{ route('admin.packages.store') }}'" method="POST" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <div class="space-y-1.5">
                        <label for="package-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم الباقة') }}</label>
                        <input id="package-name" name="name" x-model="form.name" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="package-price" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السعر (₪)') }}</label>
                            <input id="package-price" type="number" step="0.01" min="0" name="price" x-model="form.price" required dir="ltr" placeholder="0.00" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="package-device-limit" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('حد الأجهزة') }}</label>
                            <input id="package-device-limit" type="number" min="0" name="deviceLimit" x-model="form.deviceLimit" required dir="ltr" placeholder="0" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <span class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المزايا المفعَّلة') }}</span>
                        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                            @foreach (\App\Enums\PackageFeature::cases() as $feature)
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 transition-colors hover:bg-surface-container"
                                       title="{{ $feature->description() }}">
                                    <input type="checkbox" name="featureKeys[]" value="{{ $feature->value }}"
                                           :checked="form.featureKeys.includes('{{ $feature->value }}')"
                                           @change="$event.target.checked ? form.featureKeys.push('{{ $feature->value }}') : form.featureKeys = form.featureKeys.filter(k => k !== '{{ $feature->value }}')"
                                           class="mt-0.5 size-4 shrink-0 rounded border-outline-variant text-primary focus:ring-primary" />
                                    <span class="min-w-0">
                                        <span class="block font-body-sm font-bold text-on-surface">{{ $feature->label() }}</span>
                                        <span class="block font-body-sm text-body-sm text-on-surface-variant">{{ $feature->description() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="package-features" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مزايا إضافية (سطر لكل ميزة)') }}</label>
                        <textarea id="package-features" name="features" x-model="form.features" rows="3" placeholder="{{ __('نص حر يظهر على بطاقة الباقة') }}" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting" class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50" x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : (mode === 'edit' ? '{{ __('حفظ') }}' : '{{ __('إضافة') }}')"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
