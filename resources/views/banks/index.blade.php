@extends('layouts.app')

@section('title', __('البنوك والمحافظ - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: '{{ old('id') ? 'edit' : 'add' }}',
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
            form: { id: '{{ old('id', '') }}', name: '{{ old('name', '') }}', type: '{{ old('type', 'bank') }}', code: '{{ old('code', '') }}', phone: '{{ old('phone', '') }}', active: {{ old('active', '1') ? 'true' : 'false' }} },
            openAdd() { this.mode = 'add'; this.submitting = false; this.form = { id: '', name: '', type: 'bank', code: '', phone: '', active: true }; this.open = true; },
            openEdit(bank) { this.mode = 'edit'; this.submitting = false; this.form = { ...bank }; this.open = true; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('البنوك والمحافظ') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('أدر وسائل الدفع التي تستقبل بها الحوالات.') }}</p>
            </div>
            <button type="button" @click="openAdd()"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('إضافة بنك / محفظة') }}
            </button>
        </header>

        <form method="GET" action="{{ route('banks.index') }}" class="relative max-w-md">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث عن بنك...') }}" aria-label="{{ __('بحث') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($banks->isEmpty())
                <div class="flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">account_balance</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد بنوك أو محافظ بعد') }}</p>
                    <button type="button" @click="openAdd()" class="font-body-sm font-bold text-primary hover:underline">{{ __('أضف أول وسيلة دفع') }}</button>
                </div>
            @else
                <x-table-responsive :headers="[__('الاسم'), __('النوع'), __('الرمز'), __('واتساب'), __('الحالة'), __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($banks as $b)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <span class="material-symbols-outlined !text-xl">{{ $b->type->value === 'wallet' ? 'account_balance_wallet' : 'account_balance' }}</span>
                                        </div>
                                        <span class="font-body-sm font-bold">{{ $b->name }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-full bg-surface-container-high px-3 py-1 text-[11px] font-bold text-on-surface-variant">
                                        {{ $b->type->value === 'wallet' ? __('محفظة') : __('بنك') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant" dir="ltr">{{ $b->code }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant" dir="ltr">{{ $b->phone ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @include('banks._toggle', ['b' => $b])
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="openEdit(@js(['id' => $b->id, 'name' => $b->name, 'type' => $b->type->value, 'code' => $b->code, 'phone' => $b->phone, 'active' => (bool) $b->active]))"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                            <span class="material-symbols-outlined !text-xl">edit</span>
                                        </button>
                                        @include('banks._delete', ['b' => $b])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($banks as $b)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined !text-xl">{{ $b->type->value === 'wallet' ? 'account_balance_wallet' : 'account_balance' }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-body-sm font-bold">{{ $b->name }}</p>
                                        <p class="text-[11px] text-on-surface-variant">{{ $b->type->value === 'wallet' ? __('محفظة') : __('بنك') }} · <span dir="ltr">{{ $b->code }}</span></p>
                                    </div>
                                    @include('banks._toggle', ['b' => $b])
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2 border-t border-outline-variant/50 pt-3">
                                    <span class="text-[11px] text-on-surface-variant" dir="ltr">{{ $b->phone ?: '—' }}</span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="openEdit(@js(['id' => $b->id, 'name' => $b->name, 'type' => $b->type->value, 'code' => $b->code, 'phone' => $b->phone, 'active' => (bool) $b->active]))"
                                            class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                            <span class="material-symbols-outlined !text-lg">edit</span>
                                        </button>
                                        @include('banks._delete', ['b' => $b])
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </section>

        {{-- ══════════════ add / edit modal ══════════════ --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false"
                x-init="$watch('open', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="bank-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">

                <h3 id="bank-modal-title" class="border-b border-outline-variant/60 px-6 py-4 font-headline-md text-headline-md" x-text="mode === 'edit' ? '{{ __('تعديل وسيلة الدفع') }}' : '{{ __('إضافة وسيلة دفع') }}'"></h3>

                <form :action="mode === 'edit' ? '{{ url('banks') }}/' + form.id : '{{ route('banks.store') }}'" method="POST" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="id" :value="form.id">
                    <input type="hidden" name="active" :value="form.active ? 1 : 0">

                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input name="name" x-model="form.name" required placeholder="{{ __('مثال: بنك فلسطين') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('النوع') }}</label>
                            <select name="type" x-model="form.type"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                <option value="bank">{{ __('بنك') }}</option>
                                <option value="wallet">{{ __('محفظة') }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الرمز') }}</label>
                            <input name="code" x-model="form.code" required dir="ltr" placeholder="BOP"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم واتساب (اختياري)') }}</label>
                        <input name="phone" x-model="form.phone" dir="ltr" placeholder="+972595817016"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="flex items-start gap-1.5 font-body-sm text-body-sm text-on-surface-variant">
                            <span class="material-symbols-outlined !text-base">chat</span>
                            {{ __('يُرسَل إشعار بالطلبات الجديدة إلى هذا الرقم عبر واتساب.') }}
                        </p>
                    </div>

                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                        <span class="font-body-sm font-bold text-on-surface">{{ __('نشط') }}</span>
                        <input type="checkbox" x-model="form.active" class="h-5 w-5 rounded accent-primary" />
                    </label>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : (mode === 'edit' ? '{{ __('حفظ التغييرات') }}' : '{{ __('إضافة') }}')"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
