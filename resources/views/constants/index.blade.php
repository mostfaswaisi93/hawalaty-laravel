@extends('layouts.app')

@section('title', __('الثوابت — حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ listOpen: {{ $errors->has('title') ? 'true' : 'false' }}, submitting: false, confirmOpen: false, confirmMessage: '', confirmFormId: null }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الثوابت') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('قوائم قيم جاهزة تُعيد استخدامها في عملك.') }}</p>
            </div>
            <button type="button" @click="listOpen = true; submitting = false"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('قائمة جديدة') }}
            </button>
        </header>

        @if ($lists->isEmpty())
            <div class="tonal-elevation flex flex-col items-center gap-3 rounded-3xl border border-dashed border-outline-variant bg-surface-container-lowest py-20 text-center">
                <span class="material-symbols-outlined !text-5xl text-outline/60">tune</span>
                <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد قوائم بعد') }}</p>
                <button type="button" @click="listOpen = true; submitting = false" class="font-body-sm font-bold text-primary hover:underline">{{ __('أنشئ أول قائمة') }}</button>
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-2">
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
                            <button type="button" aria-label="{{ __('حذف القائمة') }}"
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
                                            <form method="POST" action="{{ route('constants.items.destroy', [$list->id, $item->id]) }}" class="inline-flex">
                                                @csrf @method('DELETE')
                                                <button type="submit" aria-label="{{ __('حذف') }}" class="flex h-5 w-5 items-center justify-center rounded-full text-outline transition-colors hover:bg-error/15 hover:text-error">
                                                    <span class="material-symbols-outlined !text-base">close</span>
                                                </button>
                                            </form>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('constants.items.store', $list->id) }}" class="flex items-center gap-2 border-t border-outline-variant/50 p-4">
                            @csrf
                            <input name="value" required placeholder="{{ __('أضِف عنصراً...') }}"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 text-body-sm outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            <button type="submit" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary text-on-primary transition-colors hover:bg-primary-container hover:text-on-primary-container" aria-label="{{ __('إضافة عنصر') }}">
                                <span class="material-symbols-outlined">add</span>
                            </button>
                        </form>
                    </section>
                @endforeach
            </div>
        @endif

        {{-- new list modal --}}
        <div x-show="listOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="listOpen" x-transition.opacity></div>
            <div x-show="listOpen" @keydown.escape.window="listOpen = false"
                x-init="$watch('listOpen', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="list-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-3xl bg-surface-container-lowest p-6 shadow-2xl">
                <h3 id="list-modal-title" class="mb-5 font-headline-md text-headline-md">{{ __('قائمة جديدة') }}</h3>
                <form method="POST" action="{{ route('constants.store') }}" class="space-y-4" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('عنوان القائمة') }}</label>
                        <input name="title" value="{{ old('title') }}" required placeholder="{{ __('مثال: طرق التوصيل') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="listOpen = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
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
