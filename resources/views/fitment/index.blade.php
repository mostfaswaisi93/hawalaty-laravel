@extends('layouts.app')

@section('title', __('بحث القطع - حوالاتي'))

@php $canManage = auth('tenant')->user()?->hasPermission(\App\Enums\PermissionKey::MenuManage); @endphp

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            makes: {{ \Illuminate\Support\Js::from($makes->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'models' => $m->models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name])->values(),
            ])->values()) }},
            makeId: @js($model?->vehicle_make_id ?? ''),
            get models() { return this.makes.find(m => m.id === this.makeId)?.models || [] },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('بحث القطع') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('السؤال ليس «هل القطعة متوفرة» بل «هل تناسب هذه السيارة».') }}</p>
            </div>
            @if ($canManage)
                <a href="{{ route('fitment.vehicles') }}"
                    class="flex items-center gap-2 rounded-xl bg-surface-container px-5 py-2.5 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined !text-xl">directions_car</span>
                    {{ __('المركبات') }}
                </a>
            @endif
        </header>

        <x-card title="{{ __('بحث بالمركبة') }}" class="reveal-up" style="--d:60ms">
            <form method="GET" action="{{ route('fitment.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="space-y-1.5">
                    <label for="finder-make" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الماركة') }}</label>
                    <select id="finder-make" x-model="makeId"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                        <option value="">{{ __('اختر') }}</option>
                        <template x-for="make in makes" :key="make.id">
                            <option :value="make.id" x-text="make.name"></option>
                        </template>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="finder-model" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الموديل') }}</label>
                    <select id="finder-model" name="modelId"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                        <option value="">{{ __('اختر') }}</option>
                        <template x-for="m in models" :key="m.id">
                            <option :value="m.id" :selected="m.id === @js($modelId ?? '')" x-text="m.name"></option>
                        </template>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="finder-year" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('سنة الصنع') }}</label>
                    <input id="finder-year" type="number" min="1900" max="2100" name="year" value="{{ $year }}" dir="ltr"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                </div>

                <div class="space-y-1.5">
                    <label for="finder-engine" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المحرك (اختياري)') }}</label>
                    <input id="finder-engine" name="engine" value="{{ $engine }}" placeholder="{{ __('مثال: 1.6') }}" dir="ltr"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                </div>

                <div class="sm:col-span-4">
                    <button type="submit"
                        class="w-full rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 sm:w-auto">
                        {{ __('ابحث عن القطع') }}
                    </button>
                </div>
            </form>
        </x-card>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="{{ __('بحث برقم القطعة') }}" subtitle="{{ __('رقم الوكالة أو أي رقم بديل — المسافات والشرطات لا تهم.') }}" class="reveal-up" style="--d:100ms">
                <form method="GET" action="{{ route('fitment.index') }}" class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
                    <input type="text" name="reference" value="{{ $reference }}" placeholder="{{ __('مثال: 0 986 424 815') }}" aria-label="{{ __('رقم القطعة') }}" dir="ltr"
                        class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
                </form>

                @if ($reference !== '')
                    @if ($matches->isEmpty())
                        <p class="mt-4 rounded-xl bg-error/10 p-4 font-body-sm text-error">{{ __('لا توجد قطعة بهذا الرقم.') }}</p>
                    @else
                        <ul class="mt-4 divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                            @foreach ($matches as $match)
                                <li class="px-4 py-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-body-lg font-bold">{{ $match->name }}</p>
                                            <p class="font-body-sm text-outline" dir="ltr">
                                                {{ $match->references->pluck('reference')->take(4)->implode(' · ') ?: $match->sku }}
                                            </p>
                                        </div>
                                        @if ($canManage)
                                            <a href="{{ route('fitment.edit', $match->id) }}" class="font-body-sm font-bold text-primary hover:underline">{{ __('الملاءمة') }}</a>
                                        @endif
                                    </div>
                                    @if ($match->isSuperseded())
                                        <p class="mt-1 inline-flex items-center gap-1 rounded-lg bg-secondary-container/40 px-2 py-1 font-body-sm text-secondary">
                                            <span class="material-symbols-outlined !text-sm">swap_horiz</span>
                                            {{ __('استُبدلت بـ :item', ['item' => $match->supersededBy?->name]) }}
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </x-card>

            <x-card title="{{ __('قراءة رقم الهيكل (VIN)') }}" subtitle="{{ __('يُستخرج منه الصانع وسنة الصنع فقط — الموديل والمحرك يحتاجان قاعدة بيانات مرخّصة.') }}" class="reveal-up" style="--d:140ms">
                <form method="GET" action="{{ route('fitment.index') }}" class="relative">
                    <input type="text" name="vin" value="{{ $vin }}" maxlength="24" placeholder="{{ __('17 رمزًا') }}" aria-label="{{ __('رقم الهيكل') }}" dir="ltr"
                        class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest px-4 py-3 font-numeral-lg tracking-widest outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                </form>

                @if ($decoded)
                    <dl class="mt-4 space-y-2">
                        <div class="flex justify-between gap-3">
                            <dt class="font-body-sm text-on-surface-variant">{{ __('الطول') }}</dt>
                            <dd class="font-body-sm font-bold {{ $decoded['valid'] ? 'text-success' : 'text-error' }}">
                                {{ $decoded['valid'] ? __('سليم (17)') : __('غير مكتمل') }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="font-body-sm text-on-surface-variant">{{ __('رمز الصانع WMI') }}</dt>
                            <dd class="font-numeral-lg font-bold" dir="ltr">{{ $decoded['wmi'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="font-body-sm text-on-surface-variant">{{ __('الماركة') }}</dt>
                            <dd class="font-body-sm font-bold">{{ $decoded['make']?->name ?? __('غير معروفة — سجّل WMI على الماركة') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="font-body-sm text-on-surface-variant">{{ __('سنة الصنع') }}</dt>
                            <dd class="font-numeral-lg font-bold" dir="ltr">{{ $decoded['year'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="font-body-sm text-on-surface-variant">{{ __('رقم التحقّق') }}</dt>
                            <dd class="font-body-sm {{ $decoded['checkDigit'] ? 'text-success' : 'text-on-surface-variant' }}">
                                {{ $decoded['checkDigit'] ? __('مطابق') : __('غير مطابق — راجع القراءة') }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 font-body-sm text-outline">{{ __('رقم التحقّق إلزامي في أمريكا الشمالية فقط؛ عدم مطابقته ليس دليلًا على خطأ.') }}</p>
                @endif
            </x-card>
        </div>

        @if (filled($modelId))
            <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-headline-md text-headline-md">
                        {{ $model?->fullName() }}
                        @if ($year)
                            <span class="text-on-surface-variant" dir="ltr">{{ $year }}</span>
                        @endif
                    </h2>
                    <span class="font-body-sm text-on-surface-variant">{{ __(':count قطعة مطابقة', ['count' => $parts->count()]) }}</span>
                </div>

                @if ($parts->isEmpty())
                    <div class="flex flex-col items-center gap-3 py-12 text-center">
                        <span class="material-symbols-outlined !text-5xl text-outline/50">search_off</span>
                        <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد قطع مسجّلة لهذه المركبة') }}</p>
                    </div>
                @else
                    <x-table-responsive :headers="[
                        $naming->item(),
                        __('السنوات'),
                        __('المحرك'),
                        ['label' => __('الرصيد'), 'align' => 'end'],
                        ['label' => __('السعر'), 'align' => 'end'],
                        __(''),
                    ]">
                        <x-slot:table>
                            @foreach ($parts as $row)
                                <tr class="transition-colors hover:bg-surface-container-low/50">
                                    <td class="px-4 py-3">
                                        <p class="font-body-sm font-bold">{{ $row['item']->name }}</p>
                                        <p class="font-body-sm text-outline">{{ $row['item']->menuCategory?->name }}</p>
                                        @if ($row['item']->isSuperseded())
                                            <p class="font-body-sm text-secondary">{{ __('استُبدلت بـ :item', ['item' => $row['item']->supersededBy?->name]) }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-body-sm" dir="ltr">{{ $row['fitment']->yearLabel() }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant" dir="ltr">{{ $row['fitment']->engine ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $row['on_hand'] <= 0 ? 'text-error' : 'text-success' }}" dir="ltr">
                                        {{ number_format($row['on_hand'], 0) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">
                                        {{ number_format((float) $row['item']->price, 2) }}
                                        @if ((float) $row['item']->core_charge > 0)
                                            <span class="block font-body-sm text-outline">+{{ number_format((float) $row['item']->core_charge, 2) }} {{ __('تأمين') }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if ($canManage)
                                            <a href="{{ route('fitment.edit', $row['item']->id) }}"
                                                class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                                aria-label="{{ __('ملاءمة :item', ['item' => $row['item']->name]) }}">
                                                <span class="material-symbols-outlined !text-xl">edit</span>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot:table>

                        <x-slot:cards>
                            @foreach ($parts as $row)
                                <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="font-body-lg font-bold">{{ $row['item']->name }}</p>
                                        <span class="whitespace-nowrap font-numeral-lg font-bold {{ $row['on_hand'] <= 0 ? 'text-error' : 'text-success' }}" dir="ltr">
                                            {{ number_format($row['on_hand'], 0) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 font-body-sm text-on-surface-variant" dir="ltr">
                                        {{ $row['fitment']->yearLabel() }}{{ $row['fitment']->engine ? ' · '.$row['fitment']->engine : '' }}
                                    </p>
                                </div>
                            @endforeach
                        </x-slot:cards>
                    </x-table-responsive>
                @endif
            </section>
        @endif
    </div>
@endsection
