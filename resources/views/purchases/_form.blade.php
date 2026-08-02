{{--
    Purchase editor, shared by create and by a draft being revised.

    Batch number and expiry are demanded only where the trade demands them; the
    same form serves a bookshop and a pharmacy because the vertical decides which
    columns exist, not the template.
--}}
@php
    $tracksBatches = $business->has(\App\Enums\BusinessCapability::Batches);
    $existing = ($purchase ?? null)?->lines->map(fn ($line) => [
        'itemId' => $line->menu_item_id,
        'quantity' => (float) $line->quantity,
        'unitCost' => (float) $line->unit_cost,
        'taxRate' => (float) $line->tax_rate,
        'batchNumber' => $line->batch_number ?? '',
        'expiresAt' => $line->expires_at?->format('Y-m-d') ?? '',
    ])->values()->all() ?? [];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
    x-data="{
        submitting: false,
        catalog: {{ \Illuminate\Support\Js::from($items->map(fn ($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'cost' => (float) $i->cost,
            'taxRate' => (float) $i->tax_rate,
            'unit' => $i->purchaseUnit()->shortLabel(),
            'step' => $i->purchaseUnit()->step(),
        ])->values()) }},
        otherCosts: {{ (float) (($purchase ?? null)?->other_costs ?? 0) }},
        lines: {{ \Illuminate\Support\Js::from($existing ?: [['itemId' => '', 'quantity' => '', 'unitCost' => '', 'taxRate' => '', 'batchNumber' => '', 'expiresAt' => '']]) }},
        itemById(id) { return this.catalog.find(i => i.id === id) || null },
        onItemChange(index) {
            const item = this.itemById(this.lines[index].itemId);
            if (! item) return;
            if (! this.lines[index].unitCost) this.lines[index].unitCost = item.cost || '';
            if (this.lines[index].taxRate === '' ) this.lines[index].taxRate = item.taxRate;
        },
        addLine() { this.lines.push({ itemId: '', quantity: '', unitCost: '', taxRate: '', batchNumber: '', expiresAt: '' }) },
        removeLine(index) { if (this.lines.length > 1) this.lines.splice(index, 1) },
        lineNet(line) { return (parseFloat(line.quantity) || 0) * (parseFloat(line.unitCost) || 0) },
        lineTax(line) { return this.lineNet(line) * ((parseFloat(line.taxRate) || 0) / 100) },
        get subtotal() { return this.lines.reduce((sum, l) => sum + this.lineNet(l), 0) },
        get taxTotal() { return this.lines.reduce((sum, l) => sum + this.lineTax(l), 0) },
        get grandTotal() { return this.subtotal + this.taxTotal + (parseFloat(this.otherCosts) || 0) },
        money(value) { return (value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    }"
    @submit="submitting = true">
    @csrf
    @if (($method ?? 'POST') === 'PUT')
        @method('PUT')
    @endif

    <x-card title="{{ __('بيانات الأمر') }}">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <label for="purchase-supplier" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المورد') }}</label>
                <select id="purchase-supplier" name="supplierId"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('بدون مورد محدد') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplierId', ($purchase ?? null)?->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="purchase-location" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('موقع الاستلام') }}</label>
                <select id="purchase-location" name="locationId" required
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('locationId', ($purchase ?? null)?->location_id ?? $locations->firstWhere('is_default', true)?->id) === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('locationId')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-1.5">
                <label for="purchase-reference" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم الأمر الداخلي') }}</label>
                <input id="purchase-reference" name="reference" value="{{ old('reference', ($purchase ?? null)?->reference) }}" maxlength="64" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>

            <div class="space-y-1.5">
                <label for="purchase-invoice" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رقم فاتورة المورد') }}</label>
                <input id="purchase-invoice" name="invoiceNumber" value="{{ old('invoiceNumber', ($purchase ?? null)?->invoice_number) }}" maxlength="64" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>

            <div class="space-y-1.5">
                <label for="purchase-ordered-at" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تاريخ الأمر') }}</label>
                <input id="purchase-ordered-at" type="date" name="orderedAt" value="{{ old('orderedAt', ($purchase ?? null)?->ordered_at?->format('Y-m-d') ?? now()->toDateString()) }}" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>

            <div class="space-y-1.5">
                <label for="purchase-other-costs" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('نقل ومصاريف أخرى') }}</label>
                <input id="purchase-other-costs" type="number" step="0.01" min="0" name="otherCosts" x-model="otherCosts" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                <p class="font-body-sm text-outline">{{ __('تُوزَّع على الأصناف بالقيمة لتصبح جزءًا من التكلفة الفعلية.') }}</p>
            </div>
        </div>
    </x-card>

    <x-card title="{{ __('الأصناف') }}">
        <x-slot:actions>
            <button type="button" @click="addLine()"
                class="flex items-center gap-1.5 rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                <span class="material-symbols-outlined !text-lg">add</span>
                {{ __('سطر') }}
            </button>
        </x-slot:actions>

        @error('lines')<p class="mb-3 font-body-sm text-error">{{ $message }}</p>@enderror

        <div class="space-y-3">
            <template x-for="(line, index) in lines" :key="index">
                <div class="rounded-2xl border border-outline-variant bg-surface-container-low/40 p-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                        <div class="space-y-1.5 sm:col-span-5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'line-item-' + index">{{ $naming->item() }}</label>
                            <select :id="'line-item-' + index" :name="'lines[' + index + '][itemId]'" x-model="line.itemId" @change="onItemChange(index)" required
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                <option value="">{{ __('اختر') }}</option>
                                <template x-for="item in catalog" :key="item.id">
                                    <option :value="item.id" x-text="item.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'line-qty-' + index">
                                {{ __('الكمية') }} <span class="text-outline" x-text="itemById(line.itemId)?.unit || ''"></span>
                            </label>
                            <input :id="'line-qty-' + index" type="number" :step="itemById(line.itemId)?.step || '0.001'" min="0" :name="'lines[' + index + '][quantity]'" x-model="line.quantity" required dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'line-cost-' + index">{{ __('تكلفة الوحدة') }}</label>
                            <input :id="'line-cost-' + index" type="number" step="0.001" min="0" :name="'lines[' + index + '][unitCost]'" x-model="line.unitCost" required dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'line-tax-' + index">{{ __('الضريبة %') }}</label>
                            <input :id="'line-tax-' + index" type="number" step="0.01" min="0" max="100" :name="'lines[' + index + '][taxRate]'" x-model="line.taxRate" dir="ltr"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>

                        <div class="flex items-end sm:col-span-1">
                            <button type="button" @click="removeLine(index)" :disabled="lines.length === 1"
                                class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error disabled:cursor-not-allowed disabled:opacity-40"
                                :aria-label="@js(__('حذف السطر'))">
                                <span class="material-symbols-outlined !text-xl">delete</span>
                            </button>
                        </div>

                        @if ($tracksBatches)
                            <div class="space-y-1.5 sm:col-span-3">
                                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'line-batch-' + index">{{ __('رقم اللوط') }}</label>
                                <input :id="'line-batch-' + index" :name="'lines[' + index + '][batchNumber]'" x-model="line.batchNumber" required maxlength="64" dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>
                            <div class="space-y-1.5 sm:col-span-3">
                                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'line-expiry-' + index">{{ __('تاريخ الصلاحية') }}</label>
                                <input :id="'line-expiry-' + index" type="date" :name="'lines[' + index + '][expiresAt]'" x-model="line.expiresAt" required dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>
                        @endif

                        <div class="flex items-end justify-end sm:col-span-6">
                            <p class="font-numeral-lg font-bold" dir="ltr">
                                <span x-text="money(lineNet(line) + lineTax(line))"></span> ₪
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-6 space-y-2 border-t border-outline-variant/50 pt-5">
            <div class="flex justify-between font-body-lg">
                <span class="text-on-surface-variant">{{ __('المجموع قبل الضريبة') }}</span>
                <span class="font-numeral-lg" dir="ltr"><span x-text="money(subtotal)"></span> ₪</span>
            </div>
            <div class="flex justify-between font-body-lg">
                <span class="text-on-surface-variant">{{ __('الضريبة') }}</span>
                <span class="font-numeral-lg" dir="ltr"><span x-text="money(taxTotal)"></span> ₪</span>
            </div>
            <div class="flex justify-between font-body-lg">
                <span class="text-on-surface-variant">{{ __('نقل ومصاريف') }}</span>
                <span class="font-numeral-lg" dir="ltr"><span x-text="money(parseFloat(otherCosts) || 0)"></span> ₪</span>
            </div>
            <div class="flex justify-between border-t border-outline-variant/50 pt-2 font-headline-md text-headline-md font-bold">
                <span>{{ __('الإجمالي') }}</span>
                <span class="font-numeral-lg" dir="ltr"><span x-text="money(grandTotal)"></span> ₪</span>
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="space-y-1.5">
            <label for="purchase-notes" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('ملاحظات') }}</label>
            <textarea id="purchase-notes" name="notes" rows="2" maxlength="1000"
                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">{{ old('notes', ($purchase ?? null)?->notes) }}</textarea>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('purchases.index') }}" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</a>
            <button type="submit" :disabled="submitting"
                class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js($submitLabel)">{{ $submitLabel }}</button>
        </div>
    </x-card>
</form>
