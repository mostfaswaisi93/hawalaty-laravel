@extends('layouts.app')

@section('title', $parent->name.' - '.__('التنويعات'))

@php
    $hasVariants = $parent->variants->isNotEmpty();
    $selectedAxes = $parent->variantAxes->pluck('id')->all();
@endphp

@section('content')
    <div class="page-enter space-y-6" x-data="{ generator: {{ $hasVariants ? 'false' : 'true' }}, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0">
                <a href="{{ route('variants.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('التنويعات') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $parent->name }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    {{ $hasVariants
                        ? __(':count تنويعة. الموديل نفسه لا يُباع — يُباع أبناؤه.', ['count' => $parent->variants->count()])
                        : __('لا تنويعات بعد. اختر المحاور وقيمها ليولّد النظام التوليفات.') }}
                </p>
            </div>
            @if ($hasVariants)
                <button type="button" @click="generator = !generator"
                    class="flex items-center gap-2 rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined !text-xl">add</span>
                    {{ __('توسيع المصفوفة') }}
                </button>
            @endif
        </header>

        <div x-show="generator" x-cloak>
            <form method="POST" action="{{ route('variants.generate', $parent->id) }}" @submit="submitting = true">
                @csrf
                <x-card title="{{ __('محاور التنويع') }}" subtitle="{{ __('اختر القيم من كل محور. التوليد يضيف الناقص فقط ولا يمسّ التنويعات القائمة.') }}">
                    @if ($axes->isEmpty())
                        <p class="rounded-xl bg-secondary-container/10 p-4 font-body-sm text-secondary">
                            {{ __('لا توجد قوائم ثابتة بقيم. أضف «المقاسات» و«الألوان» من صفحة الثوابت أولًا.') }}
                        </p>
                    @else
                        <div class="space-y-4">
                            @foreach ($axes as $axis)
                                <fieldset class="rounded-2xl border p-4 {{ in_array($axis->id, $selectedAxes, true) ? 'border-primary/50 bg-primary/5' : 'border-outline-variant' }}">
                                    <legend class="px-1 font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ $axis->title }}</legend>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($axis->items as $value)
                                            <input id="axis-{{ $axis->id }}-{{ $value->id }}" type="checkbox" name="axes[{{ $axis->id }}][]" value="{{ $value->id }}" class="peer sr-only" />
                                            <label for="axis-{{ $axis->id }}-{{ $value->id }}"
                                                class="inline-flex cursor-pointer items-center rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 font-body-sm transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-on-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary">
                                                {{ $value->value }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>

                        @error('axes')<p class="mt-3 font-body-sm text-error">{{ $message }}</p>@enderror

                        <div class="mt-6 flex justify-end gap-3 border-t border-outline-variant/50 pt-5">
                            @if ($hasVariants)
                                <button type="button" @click="generator = false" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                            @endif
                            <button type="submit" :disabled="submitting"
                                class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                x-text="submitting ? @js(__('جارٍ التوليد...')) : @js(__('توليد التنويعات'))">{{ __('توليد التنويعات') }}</button>
                        </div>
                    @endif
                </x-card>
            </form>
        </div>

        @if ($hasVariants)
            @if ($locations->count() > 1)
                <form method="GET" action="{{ route('variants.edit', $parent->id) }}" class="max-w-xs">
                    <select name="locationId" onchange="this.form.submit()" aria-label="{{ __('الموقع') }}"
                        class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                        <option value="">{{ __('كل المواقع') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if ($grid['rows'] !== [])
                <x-card title="{{ __('مصفوفة المخزون') }}" subtitle="{{ __('الرصيد لكل توليفة في شبكة واحدة بدل قائمة طويلة.') }}">
                    <div class="overflow-x-auto">
                        <table class="w-full text-start">
                            <thead class="bg-surface-container-low">
                                <tr>
                                    <th class="px-3 py-2 text-start font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">
                                        {{ $parent->variantAxes->get(0)?->title }}
                                    </th>
                                    @forelse ($grid['columns'] as $column)
                                        <th class="px-3 py-2 text-center font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ $column['label'] }}</th>
                                    @empty
                                        <th class="px-3 py-2 text-center font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الرصيد') }}</th>
                                    @endforelse
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach ($grid['rows'] as $row)
                                    <tr>
                                        <th class="whitespace-nowrap px-3 py-2 text-start font-body-sm font-bold">{{ $row['label'] }}</th>
                                        @foreach (($grid['columns'] ?: [['id' => null, 'label' => '']]) as $column)
                                            @php $cell = $grid['cells'][$service->cellKey($row['id'], $column['id'])] ?? null; @endphp
                                            <td class="px-3 py-2 text-center">
                                                @if ($cell === null)
                                                    <span class="text-outline/50">—</span>
                                                @else
                                                    @php
                                                        $qty = $cell['on_hand'];
                                                        $tone = $qty <= 0 ? 'bg-error/10 text-error' : ($qty <= 3 ? 'bg-secondary-container/40 text-secondary' : 'bg-success/10 text-success');
                                                    @endphp
                                                    <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2 font-numeral-lg font-bold {{ $tone }}"
                                                        title="{{ $cell['variant']->name }}" dir="ltr">{{ number_format($qty, 0) }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($grid['loose']->isNotEmpty())
                        <p class="mt-4 font-body-sm text-outline">
                            {{ __(':count تنويعة لا تظهر في الشبكة لأنها على أكثر من محورين — تجدها في الجدول أدناه.', ['count' => $grid['loose']->count()]) }}
                        </p>
                    @endif
                </x-card>
            @endif

            <form method="POST" action="{{ route('variants.update', $parent->id) }}" x-data="{ saving: false }" @submit="saving = true">
                @csrf
                @method('PUT')

                <x-card title="{{ __('تفاصيل التنويعات') }}" subtitle="{{ __('لكل تنويعة باركود مستقل — معيار GS1 يمنح كل مقاس ولون رقم GTIN خاصًا به.') }}">
                    <x-table-responsive :headers="[
                        __('التنويعة'),
                        __('الرمز'),
                        __('الباركود'),
                        ['label' => __('السعر'), 'align' => 'end'],
                        ['label' => __('الرصيد'), 'align' => 'end'],
                        __('مفعّلة'),
                        __(''),
                    ]">
                        <x-slot:table>
                            @foreach ($parent->variants->sortBy('name') as $variant)
                                @php
                                    $entry = collect($grid['cells'])->firstWhere('variant.id', $variant->id)
                                        ?? $grid['loose']->firstWhere('variant.id', $variant->id);
                                    $qty = $entry['on_hand'] ?? 0.0;
                                @endphp
                                <tr class="transition-colors hover:bg-surface-container-low/50">
                                    <td class="px-4 py-3 font-body-sm font-bold">{{ $variant->name }}</td>
                                    <td class="px-4 py-3">
                                        <input name="variants[{{ $variant->id }}][sku]" value="{{ $variant->sku }}" maxlength="64" dir="ltr"
                                            aria-label="{{ __('رمز :variant', ['variant' => $variant->name]) }}"
                                            class="w-28 rounded-lg border border-outline-variant bg-surface-container-lowest px-2 py-1.5 font-body-sm outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input name="variants[{{ $variant->id }}][barcode]" value="{{ $variant->barcode }}" maxlength="64" dir="ltr"
                                            aria-label="{{ __('باركود :variant', ['variant' => $variant->name]) }}"
                                            class="w-36 rounded-lg border border-outline-variant bg-surface-container-lowest px-2 py-1.5 font-body-sm outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <input type="number" step="0.001" min="0" name="variants[{{ $variant->id }}][price]" value="{{ (float) $variant->price }}" dir="ltr"
                                            aria-label="{{ __('سعر :variant', ['variant' => $variant->name]) }}"
                                            class="w-24 rounded-lg border border-outline-variant bg-surface-container-lowest px-2 py-1.5 text-end font-numeral-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $qty <= 0 ? 'text-error' : '' }}" dir="ltr">{{ number_format($qty, 0) }}</td>
                                    <td class="px-4 py-3">
                                        <input type="hidden" name="variants[{{ $variant->id }}][active]" value="0" />
                                        <input type="checkbox" name="variants[{{ $variant->id }}][active]" value="1" @checked($variant->active)
                                            aria-label="{{ __('تفعيل :variant', ['variant' => $variant->name]) }}"
                                            class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <button type="submit" form="delete-variant-{{ $variant->id }}"
                                            class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                            aria-label="{{ __('حذف :variant', ['variant' => $variant->name]) }}">
                                            <span class="material-symbols-outlined !text-xl">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot:table>

                        <x-slot:cards>
                            @foreach ($parent->variants->sortBy('name') as $variant)
                                <div class="space-y-2 rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                    <p class="font-body-lg font-bold">{{ $variant->name }}</p>
                                    <input name="variants[{{ $variant->id }}][sku]" value="{{ $variant->sku }}" placeholder="{{ __('الرمز') }}" dir="ltr"
                                        aria-label="{{ __('رمز :variant', ['variant' => $variant->name]) }}"
                                        class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 font-body-sm outline-none focus:ring-2 focus:ring-primary" />
                                    <input name="variants[{{ $variant->id }}][barcode]" value="{{ $variant->barcode }}" placeholder="{{ __('الباركود') }}" dir="ltr"
                                        aria-label="{{ __('باركود :variant', ['variant' => $variant->name]) }}"
                                        class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 font-body-sm outline-none focus:ring-2 focus:ring-primary" />
                                    <input type="number" step="0.001" min="0" name="variants[{{ $variant->id }}][price]" value="{{ (float) $variant->price }}" dir="ltr"
                                        aria-label="{{ __('سعر :variant', ['variant' => $variant->name]) }}"
                                        class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 font-numeral-lg outline-none focus:ring-2 focus:ring-primary" />
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" name="variants[{{ $variant->id }}][active]" value="0" />
                                        <input id="variant-active-{{ $variant->id }}" type="checkbox" name="variants[{{ $variant->id }}][active]" value="1" @checked($variant->active)
                                            class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                                        <label for="variant-active-{{ $variant->id }}" class="font-body-sm">{{ __('مفعّلة') }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </x-slot:cards>
                    </x-table-responsive>

                    <div class="mt-6 flex justify-end border-t border-outline-variant/50 pt-5">
                        <button type="submit" :disabled="saving"
                            class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="saving ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ التنويعات'))">{{ __('حفظ التنويعات') }}</button>
                    </div>
                </x-card>
            </form>

            {{-- Kept outside the edit form: nesting forms is invalid HTML. --}}
            @foreach ($parent->variants as $variant)
                <form id="delete-variant-{{ $variant->id }}" method="POST" action="{{ route('variants.destroy', [$parent->id, $variant->id]) }}" class="hidden"
                    @submit.prevent="if (confirm('{{ __('حذف هذه التنويعة؟') }}')) $el.submit()">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @endif
    </div>
@endsection
