{{-- Group, option and item-linking dialogs. Share the page's Alpine scope. --}}

<div x-show="groupOpen" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="group-dialog-title">
    <div class="fixed inset-0 bg-black/40" x-show="groupOpen" x-transition.opacity @click="groupOpen = false"></div>
    <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
        x-show="groupOpen" @keydown.escape.window="groupOpen = false"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        <x-modal-close on-close="groupOpen = false" />

        <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
            <h3 id="group-dialog-title" class="font-headline-md text-headline-md" x-text="groupMode === 'edit' ? @js(__('تعديل مجموعة خيارات')) : @js(__('مجموعة خيارات جديدة'))"></h3>
        </div>

        <form method="POST" :action="groupAction" class="space-y-4 p-6" @submit="submitting = true">
            @csrf
            <template x-if="groupMode === 'edit'"><input type="hidden" name="_method" value="PUT" /></template>

            <div class="space-y-1.5">
                <label for="group-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم المجموعة') }}</label>
                <input id="group-name" name="name" x-model="group.name" required maxlength="255" placeholder="{{ __('مثال: الحجم، الإضافات') }}"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                @error('name')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
            </div>

            <fieldset class="space-y-2">
                <legend class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('نوع الاختيار') }}</legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['single' => __('اختيار واحد'), 'multiple' => __('اختيار متعدد')] as $value => $label)
                        <label class="flex cursor-pointer items-center justify-center rounded-xl border px-3 py-2.5 transition"
                            :class="group.selection === @js($value)
                                ? 'border-primary bg-primary text-on-primary'
                                : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'">
                            <input type="radio" name="selection" value="{{ $value }}" x-model="group.selection" class="sr-only" />
                            <span class="font-body-sm">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="group-min" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أقل عدد') }}</label>
                    <input id="group-min" type="number" min="0" max="20" name="minSelect" x-model="group.minSelect" dir="ltr"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                </div>
                <div class="space-y-1.5" x-show="group.selection === 'multiple'">
                    <label for="group-max" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('أكثر عدد') }}</label>
                    <input id="group-max" type="number" min="1" max="20" name="maxSelect" x-model="group.maxSelect" dir="ltr"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="isRequired" value="0" />
                <input id="group-required" type="checkbox" name="isRequired" value="1" x-model="group.isRequired" class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                <label for="group-required" class="font-body-sm">{{ __('إلزامية — لا يُقبل الطلب بدون اختيار منها') }}</label>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0" />
                <input id="group-active" type="checkbox" name="active" value="1" x-model="group.active" class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                <label for="group-active" class="font-body-sm">{{ __('مفعّلة') }}</label>
            </div>

            <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                <button type="button" @click="groupOpen = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                <button type="submit" :disabled="submitting"
                    class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                    x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
            </div>
        </form>
    </div>
</div>

<div x-show="optionOpen" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="option-dialog-title">
    <div class="fixed inset-0 bg-black/40" x-show="optionOpen" x-transition.opacity @click="optionOpen = false"></div>
    <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
        x-show="optionOpen" @keydown.escape.window="optionOpen = false"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        <x-modal-close on-close="optionOpen = false" />

        <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
            <h3 id="option-dialog-title" class="font-headline-md text-headline-md" x-text="option.id ? @js(__('تعديل خيار')) : @js(__('خيار جديد'))"></h3>
        </div>

        <form method="POST" :action="optionAction" class="space-y-4 p-6" @submit="submitting = true">
            @csrf
            <template x-if="option.id"><input type="hidden" name="_method" value="PUT" /></template>

            <div class="space-y-1.5">
                <label for="option-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم الخيار') }}</label>
                <input id="option-name" name="name" x-model="option.name" required maxlength="255" placeholder="{{ __('مثال: جبنة زيادة، بدون بصل') }}"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
            </div>

            <div class="space-y-1.5">
                <label for="option-price" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('فرق السعر') }}</label>
                <input id="option-price" type="number" step="0.001" name="priceDelta" x-model="option.priceDelta" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                <p class="font-body-sm text-outline">{{ __('موجب للإضافة، سالب للحجم الأصغر، صفر لما لا يغيّر السعر.') }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="option-component" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('يخصم من المخزون (اختياري)') }}</label>
                <select id="option-component" name="componentItemId" x-model="option.componentItemId"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                    <option value="">{{ __('لا يخصم شيئًا') }}</option>
                    @foreach ($items as $candidate)
                        <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                    @endforeach
                </select>
                <p class="font-body-sm text-outline">{{ __('«جبنة زيادة» تأخذ جبنة فعلية من الرف؛ «بدون بصل» لا تأخذ شيئًا.') }}</p>
            </div>

            <div class="space-y-1.5" x-show="option.componentItemId">
                <label for="option-component-quantity" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الكمية المستهلكة') }}</label>
                <input id="option-component-quantity" type="number" step="0.0001" min="0" name="componentQuantity" x-model="option.componentQuantity" dir="ltr"
                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                @error('componentQuantity')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="isDefault" value="0" />
                <input id="option-default" type="checkbox" name="isDefault" value="1" x-model="option.isDefault" class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                <label for="option-default" class="font-body-sm">{{ __('مُختار افتراضيًا') }}</label>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0" />
                <input id="option-active" type="checkbox" name="active" value="1" x-model="option.active" class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                <label for="option-active" class="font-body-sm">{{ __('مفعّل') }}</label>
            </div>

            <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                <button type="button" @click="optionOpen = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                <button type="submit" :disabled="submitting"
                    class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                    x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
            </div>
        </form>
    </div>
</div>

<div x-show="linkOpen" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="link-dialog-title">
    <div class="fixed inset-0 bg-black/40" x-show="linkOpen" x-transition.opacity @click="linkOpen = false"></div>
    <div class="relative z-10 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
        x-show="linkOpen" @keydown.escape.window="linkOpen = false"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        <x-modal-close on-close="linkOpen = false" />

        <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
            <h3 id="link-dialog-title" class="font-headline-md text-headline-md">{{ __('خيارات هذا الصنف') }}</h3>
        </div>

        <form method="POST" :action="'/modifiers/items/' + link.itemId" class="space-y-3 p-6" @submit="submitting = true">
            @csrf
            @method('PUT')

            @forelse ($groups as $group)
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-outline-variant px-4 py-3 transition-colors hover:bg-surface-container-low">
                    <input type="checkbox" name="groupIds[]" value="{{ $group->id }}" x-model="link.groupIds"
                        class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                    <span class="min-w-0">
                        <span class="block font-body-lg font-bold">{{ $group->name }}</span>
                        <span class="block font-body-sm text-outline">{{ $group->selectionLabel() }}{{ $group->is_required ? ' · '.__('إلزامية') : '' }}</span>
                    </span>
                </label>
            @empty
                <p class="py-4 text-center font-body-sm text-on-surface-variant">{{ __('أنشئ مجموعة خيارات أولًا.') }}</p>
            @endforelse

            <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                <button type="button" @click="linkOpen = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                <button type="submit" :disabled="submitting"
                    class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                    x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
            </div>
        </form>
    </div>
</div>
