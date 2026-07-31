@extends('layouts.app')

@section('title', __('المصروفات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: '{{ old('id') ? 'edit' : 'add' }}',
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
            {{-- Same shape the order form hands its picker, so the shared
                 partial works here unchanged. --}}
            accounts: {{ \Illuminate\Support\Js::from($accounts->map(fn ($a) => [
                'id' => $a->id,
                'kind' => $a->kind->value,
                'holder' => $a->holder_name,
                'institution' => $a->institutionName() ?? '',
                'type' => $a->typeName() ?? '',
                'currency' => $a->currency?->symbol ?? '',
            ])->values()) }},
            {{-- Which side of the chooser is showing; follows the chosen
                 account when an existing expense is opened for edit. --}}
            payTarget: 'cash',
            form: { id: '{{ old('id', '') }}', description: '{{ old('description', '') }}', amount: '{{ old('amount', '') }}', accountId: '{{ old('accountId', '') }}', date: '{{ old('date', now()->toDateString()) }}' },
            accountById(id) { return this.accounts.find(a => a.id === id) || null; },
            accountsOfKind(kind) { return this.accounts.filter(a => a.kind === kind); },
            get currencySymbol() { return this.accountById(this.form.accountId)?.currency || '₪'; },
            {{-- Switching side clears a choice that belongs to the other one,
                 so the hidden input can never post a mismatched account. --}}
            setTarget(kind) {
                this.payTarget = kind;
                if (this.accountById(this.form.accountId)?.kind !== kind) this.form.accountId = '';
            },
            openAdd() { this.mode = 'add'; this.submitting = false; this.payTarget = 'cash'; this.form = { id: '', description: '', amount: '', accountId: '', date: '{{ now()->toDateString() }}' }; this.open = true; },
            openEdit(expense) { this.mode = 'edit'; this.submitting = false; this.form = { ...expense }; this.payTarget = this.accountById(expense.accountId)?.kind || 'cash'; this.open = true; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('المصروفات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('سجّل مصروفات عملك وتابعها.') }}</p>
            </div>
            <button type="button" @click="openAdd()" @disabled($accounts->isEmpty())
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('إضافة مصروف') }}
            </button>
        </header>

        @if ($accounts->isEmpty())
            <div class="flex items-center gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary">
                <span class="material-symbols-outlined">info</span>
                <p class="font-body-sm">{{ __('أضِف حساباً أولاً من صفحة «الحسابات» لتتمكن من تسجيل المصروفات.') }}</p>
            </div>
        @endif

        <form method="GET" action="{{ route('expenses.index') }}" class="reveal-up relative max-w-md" style="--d:60ms">
            {{-- Filtering must not throw away a chosen page size. --}}
            <x-keep-per-page />
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث في الوصف...') }}" aria-label="{{ __('بحث') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($expenses->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">payments</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مصروفات بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('الوصف'), __('الحساب'), __('التاريخ'), ['label' => __('المبلغ'), 'align' => 'end'], __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($expenses as $e)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-error/10 text-error">
                                            <span class="material-symbols-outlined !text-xl">shopping_bag</span>
                                        </div>
                                        <span class="font-body-sm font-bold">{{ $e->description }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $e->account?->label() ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $e->date->translatedFormat('j F Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-bold text-error">-<x-money :amount="$e->amount" :symbol="$e->account?->currency?->symbol ?? ''" /></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="openEdit(@js(['id' => $e->id, 'description' => $e->description, 'amount' => (float) $e->amount, 'accountId' => $e->account_id, 'date' => $e->date->toDateString()]))"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                            <span class="material-symbols-outlined !text-xl">edit</span>
                                        </button>
                                        <form method="POST" action="{{ route('expenses.destroy', $e->id) }}" id="delete-expense-row-{{ $e->id }}" class="inline-flex">
                                            @csrf @method('DELETE')
                                        </form>
                                        <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}"
                                            @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذا المصروف؟') }}'; confirmFormId = 'delete-expense-row-{{ $e->id }}'"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                            <span class="material-symbols-outlined !text-xl">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($expenses as $e)
                            <div class="reveal-up tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4"
                                style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-error/10 text-error">
                                        <span class="material-symbols-outlined !text-xl">shopping_bag</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-body-sm font-bold">{{ $e->description }}</p>
                                        <p class="text-[11px] text-on-surface-variant">{{ $e->account?->label() ?? '-' }} · {{ $e->date->translatedFormat('j M') }}</p>
                                    </div>
                                    <p class="whitespace-nowrap font-bold text-error">-<x-money :amount="$e->amount" :symbol="$e->account?->currency?->symbol ?? ''" /></p>
                                </div>
                                <div class="mt-3 flex items-center justify-end gap-1 border-t border-outline-variant/50 pt-3">
                                    <button type="button" @click="openEdit(@js(['id' => $e->id, 'description' => $e->description, 'amount' => (float) $e->amount, 'accountId' => $e->account_id, 'date' => $e->date->toDateString()]))"
                                        class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                        <span class="material-symbols-outlined !text-lg">edit</span>
                                    </button>
                                    <form method="POST" action="{{ route('expenses.destroy', $e->id) }}" id="delete-expense-card-{{ $e->id }}" class="inline-flex">
                                        @csrf @method('DELETE')
                                    </form>
                                    <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}"
                                        @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذا المصروف؟') }}'; confirmFormId = 'delete-expense-card-{{ $e->id }}'"
                                        class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error">
                                        <span class="material-symbols-outlined !text-lg">delete</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$expenses" :perPage="$perPage" />

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
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false"
                x-init="$watch('open', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="expense-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="open = false" />
                <h3 id="expense-modal-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md" x-text="mode === 'edit' ? '{{ __('تعديل المصروف') }}' : '{{ __('إضافة مصروف') }}'"></h3>
                <form :action="mode === 'edit' ? '{{ url('expenses') }}/' + form.id : '{{ route('expenses.store') }}'" method="POST" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="id" :value="form.id">

                    <div class="space-y-1.5">
                        <label for="expenses-description" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الوصف') }}</label>
                        <input id="expenses-description" name="description" x-model="form.description" required placeholder="{{ __('مثال: فاتورة كهرباء') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="expenses-amount" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المبلغ') }} <span dir="ltr" x-text="'(' + currencySymbol + ')'"></span></label>
                            <input id="expenses-amount" type="number" step="0.01" min="0" name="amount" x-model="form.amount" required dir="ltr" placeholder="0.00"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="expenses-date" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('التاريخ') }}</label>
                            <input id="expenses-date" type="date" name="date" x-model="form.date" required dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                    {{-- Which account the money left, picked the same way the order
                         form picks where money arrives: choose the channel, then the
                         account, which collapses into one card with a "تغيير" button. --}}
                    <div class="space-y-2">
                        <span class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('صُرِف من') }}</span>

                        <div class="grid grid-cols-3 gap-2 rounded-xl bg-surface-container p-1">
                            <template x-for="opt in [
                                { key: 'cash', icon: 'payments', label: '{{ __('نقدي') }}' },
                                { key: 'bank', icon: 'account_balance', label: '{{ __('بنك') }}' },
                                { key: 'wallet', icon: 'account_balance_wallet', label: '{{ __('محفظة') }}' },
                            ]" :key="opt.key">
                                <button type="button" @click="setTarget(opt.key)"
                                    class="flex flex-col items-center justify-center gap-1 rounded-lg py-2.5 font-body-sm font-bold transition-all"
                                    :class="payTarget === opt.key ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'"
                                    :aria-pressed="payTarget === opt.key ? 'true' : 'false'">
                                    <span class="material-symbols-outlined !text-xl" x-text="opt.icon"></span>
                                    <span x-text="opt.label"></span>
                                </button>
                            </template>
                        </div>

                        @include('partials._account-picker', [
                            'model' => 'form.accountId',
                            'inputName' => 'accountId',
                            'listExpr' => 'accountsOfKind(payTarget)',
                            'id' => 'expense',
                            'empty' => __('لا توجد حسابات من هذا النوع. أنشئ حساباً أولاً من صفحة الحسابات.'),
                        ])

                        @error('accountId')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : (mode === 'edit' ? '{{ __('حفظ') }}' : '{{ __('إضافة') }}')"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
