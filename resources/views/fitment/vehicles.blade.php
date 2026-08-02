@extends('layouts.app')

@section('title', __('المركبات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ makeOpen: {{ $errors->any() ? 'true' : 'false' }}, modelFor: null, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <a href="{{ route('fitment.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('بحث القطع') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('المركبات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('الماركات والموديلات التي تخدمها — لا يمكن ربط قطعة بمركبة غير مسجّلة.') }}</p>
            </div>
            <button type="button" @click="makeOpen = true"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('ماركة جديدة') }}
            </button>
        </header>

        @if ($makes->isEmpty())
            <div class="reveal-scale flex flex-col items-center gap-3 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest py-16 text-center">
                <span class="material-symbols-outlined !text-5xl text-outline/50">directions_car</span>
                <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد ماركات بعد') }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($makes as $make)
                    <x-card class="reveal-up" style="--d:{{ min($loop->index, 11) * 40 + 60 }}ms">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-headline-md text-headline-md">{{ $make->name }}</h3>
                                    @if ($make->wmi)
                                        <span class="rounded-lg bg-surface-container px-2 py-1 font-numeral-lg text-xs text-on-surface-variant" dir="ltr"
                                            title="{{ __('رمز الصانع في رقم الهيكل') }}">{{ $make->wmi }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 font-body-sm text-outline">{{ __(':count موديل', ['count' => $make->models_count]) }}</p>
                            </div>
                            <button type="button" @click="modelFor = @js($make->id)"
                                class="flex flex-shrink-0 items-center gap-1.5 rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                                <span class="material-symbols-outlined !text-lg">add</span>{{ __('موديل') }}
                            </button>
                        </div>

                        @if ($make->models->isNotEmpty())
                            <ul class="mt-4 divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                                @foreach ($make->models as $model)
                                    <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                                        <div class="min-w-0">
                                            <span class="font-body-lg">{{ $model->name }}</span>
                                            @if ($model->fitments_count > 0)
                                                <span class="ms-2 font-body-sm text-outline">{{ __(':count قطعة', ['count' => $model->fitments_count]) }}</span>
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('fitment.models.destroy', $model->id) }}"
                                            @submit.prevent="if (confirm('{{ __('حذف هذا الموديل؟') }}')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                                aria-label="{{ __('حذف :name', ['name' => $model->name]) }}">
                                                <span class="material-symbols-outlined !text-xl">delete</span>
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                @endforeach
            </div>
        @endif

        <div x-show="makeOpen" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="make-title">
            <div class="fixed inset-0 bg-black/40" x-show="makeOpen" x-transition.opacity @click="makeOpen = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="makeOpen" @keydown.escape.window="makeOpen = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="makeOpen = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="make-title" class="font-headline-md text-headline-md">{{ __('ماركة جديدة') }}</h3>
                </div>

                <form method="POST" action="{{ route('fitment.makes.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="make-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input id="make-name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="{{ __('مثال: تويوتا') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('name')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="make-wmi" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رمز الصانع WMI (اختياري)') }}</label>
                        <input id="make-wmi" name="wmi" value="{{ old('wmi') }}" maxlength="3" dir="ltr" placeholder="JTD"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 font-numeral-lg tracking-widest outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('أول ثلاثة رموز من رقم الهيكل — تسمح بقراءة الماركة من VIN تلقائيًا.') }}</p>
                        @error('wmi')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="makeOpen = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="modelFor" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="model-title">
            <div class="fixed inset-0 bg-black/40" x-show="modelFor" x-transition.opacity @click="modelFor = null"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="modelFor" @keydown.escape.window="modelFor = null"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="modelFor = null" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="model-title" class="font-headline-md text-headline-md">{{ __('موديل جديد') }}</h3>
                </div>

                <form method="POST" :action="'/fitment/makes/' + modelFor + '/models'" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="model-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input id="model-name" name="name" required maxlength="255" placeholder="{{ __('مثال: كورولا') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="modelFor = null" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
