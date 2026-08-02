@extends('layouts.app')

@section('title', __('الموردون - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: 'add',
            submitting: false,
            form: { id: '', name: '{{ old('name', '') }}', phone: '{{ old('phone', '') }}', email: '{{ old('email', '') }}', address: '{{ old('address', '') }}', taxNumber: '{{ old('taxNumber', '') }}', paymentTermsDays: '{{ old('paymentTermsDays', '') }}', notes: '{{ old('notes', '') }}', active: true },
            openAdd() { this.mode = 'add'; this.submitting = false; this.form = { id: '', name: '', phone: '', email: '', address: '', taxNumber: '', paymentTermsDays: '', notes: '', active: true }; this.open = true },
            openEdit(supplier) { this.mode = 'edit'; this.submitting = false; this.form = { ...supplier }; this.open = true },
            get action() { return this.mode === 'edit' ? '/suppliers/' + this.form.id : '{{ route('suppliers.store') }}' },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الموردون') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('من تشتري منه، وشروط الدفع معه.') }}</p>
            </div>
            <button type="button" @click="openAdd()"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('إضافة مورد') }}
            </button>
        </header>

        <form method="GET" action="{{ route('suppliers.index') }}" class="reveal-up relative max-w-md" style="--d:60ms">
            <x-keep-per-page />
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالاسم أو الهاتف...') }}" aria-label="{{ __('بحث') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($suppliers->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">store</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا يوجد موردون بعد') }}</p>
                </div>
            @else
                @php
                    $payload = fn ($s) => [
                        'id' => $s->id, 'name' => $s->name, 'phone' => $s->phone ?? '', 'email' => $s->email ?? '',
                        'address' => $s->address ?? '', 'taxNumber' => $s->tax_number ?? '',
                        'paymentTermsDays' => $s->payment_terms_days ?? '', 'notes' => $s->notes ?? '', 'active' => (bool) $s->active,
                    ];
                @endphp

                <x-table-responsive :headers="[__('المورد'), __('الهاتف'), __('الرقم الضريبي'), ['label' => __('مهلة السداد'), 'align' => 'end'], ['label' => __('أوامر الشراء'), 'align' => 'end'], __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($suppliers as $supplier)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <span class="material-symbols-outlined !text-xl">store</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-body-sm font-bold">{{ $supplier->name }}</p>
                                            @unless ($supplier->active)
                                                <span class="font-body-sm text-outline">{{ __('غير مفعّل') }}</span>
                                            @endunless
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm" dir="ltr">{{ $supplier->phone ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm" dir="ltr">{{ $supplier->tax_number ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-body-sm">{{ $supplier->payment_terms_days ? __(':days يوم', ['days' => $supplier->payment_terms_days]) : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg">{{ $supplier->purchases_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="openEdit({{ \Illuminate\Support\Js::from($payload($supplier)) }})"
                                            class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                            aria-label="{{ __('تعديل :name', ['name' => $supplier->name]) }}">
                                            <span class="material-symbols-outlined !text-xl">edit</span>
                                        </button>
                                        <form method="POST" action="{{ route('suppliers.destroy', $supplier->id) }}"
                                            @submit.prevent="if (confirm('{{ __('حذف هذا المورد؟ أوامر الشراء السابقة تبقى مرتبطة به.') }}')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                                aria-label="{{ __('حذف :name', ['name' => $supplier->name]) }}">
                                                <span class="material-symbols-outlined !text-xl">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($suppliers as $supplier)
                            <div class="reveal-up rounded-2xl border border-outline-variant bg-surface-container-lowest p-4" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $supplier->name }}</p>
                                        <p class="font-body-sm text-outline" dir="ltr">{{ $supplier->phone ?: '' }}</p>
                                    </div>
                                    <button type="button" @click="openEdit({{ \Illuminate\Support\Js::from($payload($supplier)) }})"
                                        class="rounded-lg p-2 text-on-surface-variant hover:text-primary" aria-label="{{ __('تعديل :name', ['name' => $supplier->name]) }}">
                                        <span class="material-symbols-outlined !text-xl">edit</span>
                                    </button>
                                </div>
                                <p class="mt-2 font-body-sm text-on-surface-variant">{{ __('أوامر الشراء') }}: {{ $supplier->purchases_count }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$suppliers" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="supplier-dialog-title">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>

            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="open" @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="open = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="supplier-dialog-title" class="font-headline-md text-headline-md" x-text="mode === 'edit' ? @js(__('تعديل مورد')) : @js(__('إضافة مورد'))"></h3>
                </div>

                <form method="POST" :action="action" class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2" @submit="submitting = true">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT" /></template>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="supplier-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم المورد') }}</label>
                        <input id="supplier-name" name="name" x-model="form.name" required maxlength="255"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('name')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="supplier-phone" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهاتف') }}</label>
                        <input id="supplier-phone" name="phone" x-model="form.phone" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="supplier-email" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البريد') }}</label>
                        <input id="supplier-email" type="email" name="email" x-model="form.email" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="supplier-address" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('العنوان') }}</label>
                        <input id="supplier-address" name="address" x-model="form.address"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="supplier-tax" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الرقم الضريبي') }}</label>
                        <input id="supplier-tax" name="taxNumber" x-model="form.taxNumber" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('مطلوب على الفاتورة في أنظمة الفوترة الإلكترونية.') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="supplier-terms" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مهلة السداد (يوم)') }}</label>
                        <input id="supplier-terms" type="number" min="0" max="365" name="paymentTermsDays" x-model="form.paymentTermsDays" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="supplier-notes" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('ملاحظات') }}</label>
                        <textarea id="supplier-notes" name="notes" x-model="form.notes" rows="2" maxlength="1000"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary"></textarea>
                    </div>

                    <div class="flex items-center gap-2 sm:col-span-2">
                        <input type="hidden" name="active" value="0" />
                        <input id="supplier-active" type="checkbox" name="active" value="1" x-model="form.active" class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                        <label for="supplier-active" class="font-body-sm">{{ __('مورد مفعّل') }}</label>
                    </div>

                    <div class="-mx-6 -mb-6 mt-2 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4 sm:col-span-2">
                        <button type="button" @click="open = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
