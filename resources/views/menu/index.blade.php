@extends('layouts.app')

@section('title', $naming->catalog() . ' - ' . __('حوالاتي'))

@section('content')
    @php
        $importHasErrors = $errors->has('file');
        $itemHasErrors = $errors->has('menuCategoryId') || $errors->has('price');
        $categoryHasErrors = $errors->any() && ! $importHasErrors && ! $itemHasErrors;
    @endphp
    <div class="page-enter space-y-6"
        x-data="{
            catOpen: {{ $categoryHasErrors ? 'true' : 'false' }},
            catIcon: '{{ old('icon', '') }}',
            itemOpen: {{ $itemHasErrors ? 'true' : 'false' }},
            importOpen: {{ $importHasErrors ? 'true' : 'false' }},
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
            itemMode: '{{ old('id') ? 'edit' : 'add' }}',
            itemForm: { id: '{{ old('id', '') }}', name: '{{ $itemHasErrors ? old('name', '') : '' }}', icon: '{{ old('icon', '') }}', menuCategoryId: '{{ old('menuCategoryId', '') }}', price: '{{ old('price', '') }}', active: {{ old('active', '1') ? 'true' : 'false' }} },
            openAddItem(catId) { this.itemMode = 'add'; this.submitting = false; this.itemForm = { id: '', name: '', icon: '', menuCategoryId: catId || '', price: '', active: true }; this.itemOpen = true; },
            openEditItem(item) { this.itemMode = 'edit'; this.submitting = false; this.itemForm = { ...item }; this.itemOpen = true; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ $naming->catalog() }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('نظّم فئاتك وعناصرها وأسعارها.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('menu.export') }}"
                    @mouseenter="showTipBelow($el, @js(__('تصدير CSV')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('تصدير CSV')))" @blur="hideTip()"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تصدير CSV') }}" title="{{ __('تصدير CSV') }}">
                    <span class="material-symbols-outlined !text-xl">download</span>
                </a>
                <button type="button" @click="importOpen = true; submitting = false"
                    @mouseenter="showTipBelow($el, @js(__('استيراد CSV')))" @mouseleave="hideTip()"
                    @focus="showTipBelow($el, @js(__('استيراد CSV')))" @blur="hideTip()"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('استيراد CSV') }}" title="{{ __('استيراد CSV') }}">
                    <span class="material-symbols-outlined !text-xl">upload</span>
                </button>
                <button type="button" @click="catOpen = true; submitting = false"
                    class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container" title="{{ __('إضافة :label', ['label' => $naming->category()]) }}">
                    <span class="material-symbols-outlined !text-xl">create_new_folder</span>
                    {{ __('إضافة :label', ['label' => $naming->category()]) }}
                </button>
                <button type="button" @click="openAddItem('')" @disabled($categories->isEmpty())
                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('إضافة :label', ['label' => $naming->item()]) }}
                </button>
            </div>
        </header>

        @if ($categories->isEmpty())
            <div class="reveal-scale tonal-elevation flex flex-col items-center gap-3 rounded-3xl border border-dashed border-outline-variant bg-surface-container-lowest py-20 text-center">
                <span class="material-symbols-outlined !text-5xl text-outline/60">category</span>
                <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد فئات بعد') }}</p>
                <button type="button" @click="catOpen = true; submitting = false" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('إضافة :label', ['label' => $naming->category()]) }}">{{ __('إضافة :label', ['label' => $naming->category()]) }}</button>
            </div>
        @else
            <div class="stagger space-y-5">
                @foreach ($categories as $cat)
                    <section class="tonal-elevation overflow-hidden rounded-3xl border border-outline-variant/50 bg-surface-container-lowest">
                        <div class="flex items-center justify-between gap-3 border-b border-outline-variant/50 p-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-secondary-container/15 text-secondary">
                                    <span class="material-symbols-outlined">{{ $catalogIcons->resolve($cat->icon) }}</span>
                                </div>
                                <div>
                                    <h2 class="font-headline-md text-headline-md text-on-surface">{{ $cat->name }}</h2>
                                    <p class="font-body-sm text-outline">{{ $cat->menuItems->count() }} {{ $naming->item() }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="openAddItem(@js($cat->id))"
                                    class="flex h-9 items-center gap-1 rounded-full px-3 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('إضافة :label', ['label' => $naming->item()]) }}">
                                    <span class="material-symbols-outlined !text-xl">add</span>
                                    <span class="hidden font-body-sm font-bold sm:inline">{{ $naming->item() }}</span>
                                </button>
                                <form method="POST" action="{{ route('menu.categories.destroy', $cat->id) }}" id="delete-category-{{ $cat->id }}" class="inline-flex">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button" aria-label="{{ __('حذف الفئة') }}" title="{{ __('حذف الفئة') }}"
                                    @click="confirmOpen = true; confirmMessage = '{{ __('حذف الفئة وكل أصنافها؟') }}'; confirmFormId = 'delete-category-{{ $cat->id }}'"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                    <span class="material-symbols-outlined !text-xl">delete</span>
                                </button>
                            </div>
                        </div>

                        @if ($cat->menuItems->isEmpty())
                            <div class="flex flex-col items-center gap-2 py-10 text-center">
                                <span class="material-symbols-outlined !text-4xl text-outline/50">{{ $catalogIcons->resolve($cat->icon) }}</span>
                                <p class="font-body-sm text-on-surface-variant">{{ __('لا عناصر في هذه الفئة بعد') }}</p>
                                <button type="button" @click="openAddItem(@js($cat->id))" class="font-body-sm font-bold text-primary hover:underline">{{ __('إضافة :label', ['label' => $naming->item()]) }}</button>
                            </div>
                        @else
                            <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                @foreach ($cat->menuItems as $item)
                                    <div id="item-{{ $item->id }}"
                                        class="flex scroll-mt-24 flex-col justify-between gap-3 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-3 transition-all target:ring-2 target:ring-primary hover:border-outline-variant hover:shadow-md {{ $item->active ? '' : 'opacity-60' }}">
                                        <div class="flex items-start gap-3">
                                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $item->active ? 'bg-primary/10 text-primary' : 'bg-surface-container-high text-outline' }}">
                                                <span class="material-symbols-outlined">{{ $catalogIcons->resolve($item->icon, $cat->icon) }}</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-body-lg font-bold {{ $item->active ? 'text-on-surface' : 'text-outline line-through' }}" title="{{ $item->name }}">{{ $item->name }}</p>
                                                <p class="font-numeral-lg text-body-lg font-bold text-primary" dir="ltr">{{ number_format($item->price, 2) }} ₪</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between gap-2 border-t border-outline-variant/40 pt-2">
                                            <form method="POST" action="{{ route('menu.items.update', $item->id) }}" class="inline-flex"
                                                x-data="{ toggling: false }" @submit="toggling = true">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="active" value="{{ $item->active ? 0 : 1 }}">
                                                <button type="submit" role="switch" aria-checked="{{ $item->active ? 'true' : 'false' }}"
                                                    aria-label="{{ $item->active ? __('إيقاف') : __('تفعيل') }}" title="{{ $item->active ? __('إيقاف') : __('تفعيل') }}"
                                                    :disabled="toggling" :class="toggling && 'animate-pulse opacity-60'"
                                                    class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed {{ $item->active ? 'bg-primary' : 'bg-surface-container-high' }}">
                                                    <span class="absolute top-1 h-4 w-4 rounded-full shadow transition-all {{ $item->active ? 'bg-on-primary start-1' : 'bg-on-surface-variant end-1' }}"></span>
                                                </button>
                                            </form>

                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="openEditItem(@js(['id' => $item->id, 'name' => $item->name, 'icon' => $item->icon ?? '', 'menuCategoryId' => $item->menu_category_id, 'price' => (float) $item->price, 'active' => (bool) $item->active]))"
                                                    class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}" title="{{ __('تعديل') }}">
                                                    <span class="material-symbols-outlined !text-xl">edit</span>
                                                </button>

                                                <form method="POST" action="{{ route('menu.items.destroy', $item->id) }}" id="delete-item-{{ $item->id }}" class="inline-flex">
                                                    @csrf @method('DELETE')
                                                </form>
                                                <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}"
                                                    @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذا العنصر؟') }}'; confirmFormId = 'delete-item-{{ $item->id }}'"
                                                    class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                                    <span class="material-symbols-outlined !text-xl">delete</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif

        <div x-show="catOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="catOpen" x-transition.opacity></div>
            <div x-show="catOpen" @keydown.escape.window="catOpen = false"
                x-init="$watch('catOpen', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="category-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="catOpen = false" />
                <h3 id="category-modal-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md">{{ __('إضافة :label', ['label' => $naming->category()]) }}</h3>
                <form method="POST" action="{{ route('menu.categories.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="menu-name-2" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم :label', ['label' => $naming->category()]) }}</label>
                        <input id="menu-name-2" name="name" value="{{ $categoryHasErrors ? old('name') : '' }}" required placeholder="{{ __('مثال: المشاوي') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <x-icon-picker model="catIcon" />
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="catOpen = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : '{{ __('إضافة') }}'"></button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="itemOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="itemOpen" x-transition.opacity></div>
            <div x-show="itemOpen" @keydown.escape.window="itemOpen = false"
                x-init="$watch('itemOpen', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="item-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="itemOpen = false" />
                <h3 id="item-modal-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md" x-text="itemMode === 'edit' ? @js(__('تعديل :label', ['label' => $naming->item()])) : @js(__('إضافة :label', ['label' => $naming->item()]))"></h3>
                <form :action="itemMode === 'edit' ? '{{ url('menu/items') }}/' + itemForm.id : '{{ route('menu.items.store') }}'" method="POST" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <template x-if="itemMode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="id" :value="itemForm.id">
                    <input type="hidden" name="active" :value="itemForm.active ? 1 : 0">

                    <div class="space-y-1.5">
                        <label for="menu-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم :label', ['label' => $naming->item()]) }}</label>
                        <input id="menu-name" name="name" x-model="itemForm.name" required placeholder="{{ __('مثال: شاورما لحمة') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <x-icon-picker model="itemForm.icon" />

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="menu-menu-category-id" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ $naming->category() }}</label>
                            <select id="menu-menu-category-id" name="menuCategoryId" x-model="itemForm.menuCategoryId" required
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                <option value="" disabled>{{ __('اختر...') }}</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="menu-price" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السعر (₪)') }}</label>
                            <input id="menu-price" type="number" step="0.01" min="0" name="price" x-model="itemForm.price" required dir="ltr" placeholder="0.00"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                        <span class="font-body-sm font-bold text-on-surface">{{ __('متاح للطلب') }}</span>
                        <input type="checkbox" x-model="itemForm.active" class="h-5 w-5 rounded accent-primary" />
                    </label>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="itemOpen = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : (itemMode === 'edit' ? '{{ __('حفظ التغييرات') }}' : '{{ __('إضافة') }}')"></button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="importOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="importOpen" x-transition.opacity></div>
            <div x-show="importOpen" @keydown.escape.window="importOpen = false"
                x-init="$watch('importOpen', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="import-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="importOpen = false" />
                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="import-modal-title" class="font-headline-md text-headline-md">{{ __('استيراد القائمة (CSV)') }}</h3>
                    <p class="mt-1 font-body-sm text-on-surface-variant">{{ __('اختر ملف CSV بأعمدة category, name, price, active لاستيراد أصناف جديدة أو تحديث الموجودة.') }}</p>
                </div>
                <form method="POST" action="{{ route('menu.import') }}" enctype="multipart/form-data" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" required
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none file:me-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:font-body-sm file:font-bold file:text-on-primary" />
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="importOpen = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الاستيراد...') }}' : '{{ __('استيراد') }}'"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
