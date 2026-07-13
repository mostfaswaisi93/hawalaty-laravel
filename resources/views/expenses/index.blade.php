@extends('layouts.app')

@section('title', __('المصروفات — حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: false,
            mode: 'add',
            form: { id: '', description: '', amount: '', bankId: '', date: '{{ now()->toDateString() }}' },
            openAdd() { this.mode = 'add'; this.form = { id: '', description: '', amount: '', bankId: '', date: '{{ now()->toDateString() }}' }; this.open = true; },
            openEdit(expense) { this.mode = 'edit'; this.form = { ...expense }; this.open = true; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المصروفات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('سجّل مصروفات عملك وتابعها.') }}</p>
            </div>
            <button type="button" @click="openAdd()" @disabled($banks->isEmpty())
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('إضافة مصروف') }}
            </button>
        </header>

        @if ($banks->isEmpty())
            <div class="flex items-center gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary">
                <span class="material-symbols-outlined">info</span>
                <p class="font-body-sm">{{ __('أضِف بنكاً أو محفظة أولاً من صفحة «البنوك والمحافظ» لتتمكن من تسجيل المصروفات.') }}</p>
            </div>
        @endif

        <form method="GET" action="{{ route('expenses.index') }}" class="relative max-w-md">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث في الوصف...') }}" aria-label="{{ __('بحث') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($expenses->isEmpty())
                <div class="flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">payments</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مصروفات بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('الوصف'), __('البنك'), __('التاريخ'), __('المبلغ'), __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($expenses as $e)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-error/10 text-error">
                                            <span class="material-symbols-outlined !text-xl">shopping_bag</span>
                                        </div>
                                        <span class="font-body-sm font-bold">{{ $e->description }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $e->bank?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $e->date->translatedFormat('j F Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold text-error"><span dir="ltr">-{{ number_format($e->amount, 2) }} ₪</span></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="openEdit(@js(['id' => $e->id, 'description' => $e->description, 'amount' => (float) $e->amount, 'bankId' => $e->bank_id, 'date' => $e->date->toDateString()]))"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                            <span class="material-symbols-outlined !text-xl">edit</span>
                                        </button>
                                        <form method="POST" action="{{ route('expenses.destroy', $e->id) }}" onsubmit="return confirm('{{ __('حذف هذا المصروف؟') }}')" class="inline-flex">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="{{ __('حذف') }}" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                                <span class="material-symbols-outlined !text-xl">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($expenses as $e)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-error/10 text-error">
                                        <span class="material-symbols-outlined !text-xl">shopping_bag</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-body-sm font-bold">{{ $e->description }}</p>
                                        <p class="text-[11px] text-outline">{{ $e->bank?->name ?? '—' }} · {{ $e->date->translatedFormat('j M') }}</p>
                                    </div>
                                    <p class="whitespace-nowrap font-bold text-error"><span dir="ltr">-{{ number_format($e->amount, 2) }} ₪</span></p>
                                </div>
                                <div class="mt-3 flex items-center justify-end gap-1 border-t border-outline-variant/50 pt-3">
                                    <button type="button" @click="openEdit(@js(['id' => $e->id, 'description' => $e->description, 'amount' => (float) $e->amount, 'bankId' => $e->bank_id, 'date' => $e->date->toDateString()]))"
                                        class="flex h-8 w-8 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                        <span class="material-symbols-outlined !text-lg">edit</span>
                                    </button>
                                    <form method="POST" action="{{ route('expenses.destroy', $e->id) }}" onsubmit="return confirm('{{ __('حذف هذا المصروف؟') }}')" class="inline-flex">
                                        @csrf @method('DELETE')
                                        <button type="submit" aria-label="{{ __('حذف') }}" class="flex h-8 w-8 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error">
                                            <span class="material-symbols-outlined !text-lg">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                @if ($expenses->hasPages())
                    <div class="mt-6 flex items-center justify-between gap-4 border-t border-outline-variant/50 pt-5">
                        <span class="font-body-sm text-on-surface-variant">
                            {{ __('صفحة') }} {{ $expenses->currentPage() }} {{ __('من') }} {{ $expenses->lastPage() }}
                        </span>
                        <div class="flex items-center gap-2">
                            @if ($expenses->onFirstPage())
                                <span class="flex items-center gap-1 rounded-xl border border-outline-variant/60 px-4 py-2 font-body-sm text-outline/60">
                                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span> {{ __('السابق') }}
                                </span>
                            @else
                                <a href="{{ $expenses->previousPageUrl() }}" class="flex items-center gap-1 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">
                                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span> {{ __('السابق') }}
                                </a>
                            @endif

                            @if ($expenses->hasMorePages())
                                <a href="{{ $expenses->nextPageUrl() }}" class="flex items-center gap-1 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container">
                                    {{ __('التالي') }} <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                                </a>
                            @else
                                <span class="flex items-center gap-1 rounded-xl border border-outline-variant/60 px-4 py-2 font-body-sm text-outline/60">
                                    {{ __('التالي') }} <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_forward</span>
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </section>

        {{-- add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" @click="open = false" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl">
                <h3 class="mb-5 font-headline-md text-headline-md" x-text="mode === 'edit' ? '{{ __('تعديل المصروف') }}' : '{{ __('إضافة مصروف') }}'"></h3>
                <form :action="mode === 'edit' ? '{{ url('expenses') }}/' + form.id : '{{ route('expenses.store') }}'" method="POST" class="space-y-4">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الوصف') }}</label>
                        <input name="description" x-model="form.description" required placeholder="{{ __('مثال: فاتورة كهرباء') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المبلغ (₪)') }}</label>
                            <input type="number" step="0.01" min="0" name="amount" x-model="form.amount" required dir="ltr" placeholder="0.00"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التاريخ') }}</label>
                            <input type="date" name="date" x-model="form.date" required dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البنك / المحفظة') }}</label>
                        <select name="bankId" x-model="form.bankId" required
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="" disabled>{{ __('اختر...') }}</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="open = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95"
                            x-text="mode === 'edit' ? '{{ __('حفظ') }}' : '{{ __('إضافة') }}'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
