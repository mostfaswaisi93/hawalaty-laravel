@extends('layouts.app')

@section('title', $item->name.' - '.__('الوصفة'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            submitting: false,
            catalog: {{ \Illuminate\Support\Js::from($candidates->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'cost' => (float) $c->cost,
                'unit' => $c->unit()->shortLabel(),
            ])->values()) }},
            rows: {{ \Illuminate\Support\Js::from($item->recipeComponents->map(fn ($c) => [
                'componentId' => $c->component_item_id,
                'quantity' => (float) $c->quantity,
                'wastePercent' => (float) $c->waste_percent,
            ])->values()->all() ?: [['componentId' => '', 'quantity' => '', 'wastePercent' => 0]]) }},
            byId(id) { return this.catalog.find(c => c.id === id) || null },
            add() { this.rows.push({ componentId: '', quantity: '', wastePercent: 0 }) },
            remove(index) { if (this.rows.length > 1) this.rows.splice(index, 1); else this.rows = [{ componentId: '', quantity: '', wastePercent: 0 }] },
            gross(row) {
                const waste = parseFloat(row.wastePercent) || 0;
                const qty = parseFloat(row.quantity) || 0;
                return (waste > 0 && waste < 100) ? qty / (1 - waste / 100) : qty;
            },
            rowCost(row) { return this.gross(row) * (this.byId(row.componentId)?.cost || 0) },
            get total() { return this.rows.reduce((sum, r) => sum + this.rowCost(r), 0) },
            get margin() {
                const price = {{ (float) $item->netPrice() }};
                if (price <= 0 || this.total <= 0) return null;
                return Math.round(((price - this.total) / price) * 1000) / 10;
            },
            money(v) { return (v || 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) },
        }">

        <header>
            <a href="{{ route('recipes.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('الوصفات') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $item->name }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">
                {{ $item->assemblesToOrder()
                    ? __('يُجهَّز عند الطلب — بيعه يخصم هذه المكوّنات مباشرة من المخزون.')
                    : __('يُنتَج للمخزون — المكوّنات تُخصم عند ترحيل أمر إنتاج، لا عند البيع.') }}
            </p>
        </header>

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="--d:60ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تكلفة نظرية للوحدة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr"><span x-text="money(total)"></span> ₪</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('سعر البيع') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format((float) $item->price, 3) }} ₪</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الهامش النظري') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr"
                    :class="margin !== null && margin < 0 && 'text-error'">
                    <span x-text="margin === null ? '—' : margin + '%'"></span>
                </p>
            </x-card>
        </div>

        <form method="POST" action="{{ route('recipes.update', $item->id) }}" @submit="submitting = true">
            @csrf
            @method('PUT')

            <x-card title="{{ __('المكوّنات') }}" subtitle="{{ __('الكمية لوحدة واحدة من الصنف. نسبة الفاقد تغطي التقشير والتقطيع والتبخّر.') }}">
                <x-slot:actions>
                    <button type="button" @click="add()"
                        class="flex items-center gap-1.5 rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                        <span class="material-symbols-outlined !text-lg">add</span>
                        {{ __('مكوّن') }}
                    </button>
                </x-slot:actions>

                <div class="space-y-3">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="grid grid-cols-1 gap-3 rounded-2xl border border-outline-variant bg-surface-container-low/40 p-4 sm:grid-cols-12">
                            <div class="space-y-1.5 sm:col-span-5">
                                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'component-' + index">{{ __('المكوّن') }}</label>
                                <select :id="'component-' + index" :name="'components[' + index + '][componentId]'" x-model="row.componentId" required
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                    <option value="">{{ __('اختر') }}</option>
                                    <template x-for="c in catalog" :key="c.id">
                                        <option :value="c.id" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="space-y-1.5 sm:col-span-3">
                                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'quantity-' + index">
                                    {{ __('الكمية') }} <span class="text-outline" x-text="byId(row.componentId)?.unit || ''"></span>
                                </label>
                                <input :id="'quantity-' + index" type="number" step="0.0001" min="0" :name="'components[' + index + '][quantity]'" x-model="row.quantity" required dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>

                            <div class="space-y-1.5 sm:col-span-2">
                                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'waste-' + index">{{ __('فاقد %') }}</label>
                                <input :id="'waste-' + index" type="number" step="0.01" min="0" max="99" :name="'components[' + index + '][wastePercent]'" x-model="row.wastePercent" dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>

                            <div class="flex items-end justify-between gap-2 sm:col-span-2">
                                <p class="font-numeral-lg font-bold" dir="ltr"><span x-text="money(rowCost(row))"></span></p>
                                <button type="button" @click="remove(index)"
                                    class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                    :aria-label="@js(__('حذف المكوّن'))">
                                    <span class="material-symbols-outlined !text-xl">delete</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-outline-variant/50 pt-5">
                    <a href="{{ route('recipes.index') }}" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</a>
                    <button type="submit" :disabled="submitting"
                        class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                        x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ الوصفة'))">{{ __('حفظ الوصفة') }}</button>
                </div>
            </x-card>
        </form>

        @if ($exploded->isNotEmpty())
            <x-card title="{{ __('ما يُخصم فعليًا من الرف') }}" subtitle="{{ __('بعد تفكيك الوصفات الفرعية حتى الأصناف التي يُمسك لها مخزون.') }}">
                <x-table-responsive :headers="[__('الصنف'), ['label' => __('الكمية'), 'align' => 'end'], ['label' => __('التكلفة'), 'align' => 'end']]">
                    <x-slot:table>
                        @foreach ($exploded as $row)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $row['item']->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($row['quantity'], 4), '0'), '.') }} {{ $row['item']->unit()->shortLabel() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['quantity'] * (float) $row['item']->cost, 3) }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($exploded as $row)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <p class="font-body-lg font-bold">{{ $row['item']->name }}</p>
                                <p class="mt-1 font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($row['quantity'], 4), '0'), '.') }} {{ $row['item']->unit()->shortLabel() }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            </x-card>
        @endif
    </div>
@endsection
