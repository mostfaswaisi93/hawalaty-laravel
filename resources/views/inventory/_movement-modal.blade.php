{{--
    Manual stock movements.

    Only the three a person posts by hand. Sales, receipts and count variances are
    written by the flow that caused them, so they are deliberately not offered
    here - a stock ledger anyone can type a "sale" into is not a stock ledger.
--}}
<div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    role="dialog" aria-modal="true" aria-labelledby="movement-dialog-title">
    <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>

    <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
        x-show="open" @keydown.escape.window="open = false"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        <x-modal-close on-close="open = false" />

        <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
            <h3 id="movement-dialog-title" class="font-headline-md text-headline-md">{{ __('حركة مخزنية') }}</h3>
            <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant" x-text="itemName"></p>
        </div>

        <form method="POST" action="{{ route('inventory.movements.store') }}" class="space-y-5 p-6" @submit="submitting = true">
            @csrf
            <input type="hidden" name="itemId" :value="form.itemId" />

            <fieldset class="space-y-2">
                <legend class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('نوع الحركة') }}</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ([
                        \App\Enums\StockMovementType::Adjustment,
                        \App\Enums\StockMovementType::Waste,
                        \App\Enums\StockMovementType::Transfer,
                    ] as $type)
                        <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border px-3 py-3 text-center transition"
                            :class="form.type === @js($type->value)
                                ? 'border-primary bg-primary text-on-primary'
                                : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'">
                            <input type="radio" name="type" value="{{ $type->value }}" x-model="form.type" class="sr-only" />
                            <span class="material-symbols-outlined !text-xl">{{ $type->icon() }}</span>
                            <span class="font-body-sm leading-tight">{{ $type->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="space-y-1.5">
                <label for="movement-quantity" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" x-text="quantityLabel"></label>
                <input id="movement-quantity" type="number" step="0.001" min="0" name="quantity" x-model="form.quantity" required dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                <p class="font-body-sm text-outline" x-show="form.type === 'adjustment'">
                    {{ __('يُسجَّل الفرق بين هذه الكمية والرصيد الحالي، لا الكمية نفسها.') }}
                </p>
                @error('quantity')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-1.5" x-show="{{ $locations->count() > 1 ? 'true' : 'isTransfer' }}">
                <label for="movement-location" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">
                    <span x-show="!isTransfer">{{ __('الموقع') }}</span>
                    <span x-show="isTransfer" x-cloak>{{ __('من موقع') }}</span>
                </label>
                <select id="movement-location" name="locationId" x-model="form.locationId"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('الموقع الافتراضي') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('locationId')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-1.5" x-show="isTransfer" x-cloak>
                <label for="movement-to-location" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('إلى موقع') }}</label>
                <select id="movement-to-location" name="toLocationId" x-model="form.toLocationId"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('اختر الموقع') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('toLocationId')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-1.5" x-show="!isTransfer">
                <label for="movement-reason" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('السبب') }}</label>
                <input id="movement-reason" name="reason" x-model="form.reason" maxlength="255"
                    placeholder="{{ __('مثال: تلف بالتخزين، خطأ جرد سابق') }}"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                <p class="font-body-sm text-outline">{{ __('إلزامي — الحركة بدون سبب هي ما يوجد ضبط المخزون لمنعه.') }}</p>
                @error('reason')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                <button type="button" @click="open = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                <button type="submit" :disabled="submitting"
                    class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                    x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('تسجيل الحركة'))">{{ __('تسجيل الحركة') }}</button>
            </div>
        </form>
    </div>
</div>
