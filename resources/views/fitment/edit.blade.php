@extends('layouts.app')

@section('title', $item->name.' - '.__('الملاءمة'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            submitting: false,
            makes: {{ \Illuminate\Support\Js::from($makes->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'models' => $m->models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name])->values(),
            ])->values()) }},
            fitments: {{ \Illuminate\Support\Js::from($item->fitments->map(fn ($f) => [
                'makeId' => $f->vehicleModel?->vehicle_make_id ?? '',
                'vehicleModelId' => $f->vehicle_model_id,
                'yearFrom' => $f->year_from ?? '',
                'yearTo' => $f->year_to ?? '',
                'engine' => $f->engine ?? '',
                'notes' => $f->notes ?? '',
            ])->values()->all() ?: [['makeId' => '', 'vehicleModelId' => '', 'yearFrom' => '', 'yearTo' => '', 'engine' => '', 'notes' => '']]) }},
            references: {{ \Illuminate\Support\Js::from($item->references->map(fn ($r) => [
                'type' => $r->type,
                'reference' => $r->reference,
                'brand' => $r->brand ?? '',
            ])->values()->all() ?: [['type' => 'oe', 'reference' => '', 'brand' => '']]) }},
            modelsOf(makeId) { return this.makes.find(m => m.id === makeId)?.models || [] },
            addFitment() { this.fitments.push({ makeId: '', vehicleModelId: '', yearFrom: '', yearTo: '', engine: '', notes: '' }) },
            removeFitment(i) { this.fitments.splice(i, 1); if (!this.fitments.length) this.addFitment() },
            addReference() { this.references.push({ type: 'oe', reference: '', brand: '' }) },
            removeReference(i) { this.references.splice(i, 1); if (!this.references.length) this.addReference() },
        }">

        <header>
            <a href="{{ route('fitment.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('بحث القطع') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ $item->name }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('ما تناسبه هذه القطعة، وبأي أرقام أخرى تُعرَف.') }}</p>
        </header>

        @if ($chain->isNotEmpty())
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-secondary-container/40 bg-secondary-container/10 p-4 text-secondary" style="--d:60ms">
                <span class="material-symbols-outlined flex-shrink-0">swap_horiz</span>
                <div class="min-w-0">
                    <p class="font-body-sm font-bold">{{ __('هذه القطعة مستبدَلة') }}</p>
                    <p class="mt-1 font-body-sm">{{ $item->name }} ← {{ $chain->pluck('name')->implode(' ← ') }}</p>
                    <p class="mt-1 font-body-sm">{{ __('اطلب الأخيرة في السلسلة.') }}</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('fitment.update', $item->id) }}" class="space-y-6" @submit="submitting = true">
            @csrf
            @method('PUT')

            <x-card title="{{ __('المركبات المتوافقة') }}" subtitle="{{ __('نطاق سنوات لا سنة واحدة — «من 2015 فما فوق» جواب صحيح.') }}">
                <x-slot:actions>
                    <button type="button" @click="addFitment()"
                        class="flex items-center gap-1.5 rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                        <span class="material-symbols-outlined !text-lg">add</span>{{ __('مركبة') }}
                    </button>
                </x-slot:actions>

                @if ($makes->isEmpty())
                    <p class="rounded-xl bg-secondary-container/10 p-4 font-body-sm text-secondary">
                        {{ __('لا توجد مركبات مسجّلة.') }}
                        <a href="{{ route('fitment.vehicles') }}" class="font-bold underline">{{ __('أضف ماركة وموديل أولًا.') }}</a>
                    </p>
                @else
                    <div class="space-y-3">
                        <template x-for="(row, index) in fitments" :key="index">
                            <div class="grid grid-cols-1 gap-3 rounded-2xl border border-outline-variant bg-surface-container-low/40 p-4 sm:grid-cols-12">
                                <div class="space-y-1.5 sm:col-span-3">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'fit-make-' + index">{{ __('الماركة') }}</label>
                                    <select :id="'fit-make-' + index" x-model="row.makeId" @change="row.vehicleModelId = ''"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                        <option value="">{{ __('اختر') }}</option>
                                        <template x-for="make in makes" :key="make.id">
                                            <option :value="make.id" x-text="make.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="space-y-1.5 sm:col-span-3">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'fit-model-' + index">{{ __('الموديل') }}</label>
                                    <select :id="'fit-model-' + index" :name="'fitments[' + index + '][vehicleModelId]'" x-model="row.vehicleModelId" required
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                        <option value="">{{ __('اختر') }}</option>
                                        <template x-for="m in modelsOf(row.makeId)" :key="m.id">
                                            <option :value="m.id" x-text="m.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="space-y-1.5 sm:col-span-2">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'fit-from-' + index">{{ __('من سنة') }}</label>
                                    <input :id="'fit-from-' + index" type="number" min="1900" max="2100" :name="'fitments[' + index + '][yearFrom]'" x-model="row.yearFrom" dir="ltr"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>

                                <div class="space-y-1.5 sm:col-span-2">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'fit-to-' + index">{{ __('إلى سنة') }}</label>
                                    <input :id="'fit-to-' + index" type="number" min="1900" max="2100" :name="'fitments[' + index + '][yearTo]'" x-model="row.yearTo" dir="ltr"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>

                                <div class="space-y-1.5 sm:col-span-1">
                                    <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'fit-engine-' + index">{{ __('المحرك') }}</label>
                                    <input :id="'fit-engine-' + index" :name="'fitments[' + index + '][engine]'" x-model="row.engine" maxlength="64" dir="ltr"
                                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                                </div>

                                <div class="flex items-end sm:col-span-1">
                                    <button type="button" @click="removeFitment(index)"
                                        class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                        :aria-label="@js(__('حذف السطر'))">
                                        <span class="material-symbols-outlined !text-xl">delete</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                @endif
            </x-card>

            <x-card title="{{ __('الأرقام المرجعية') }}" subtitle="{{ __('كل رقم عُرفت به هذه القطعة يجب أن يجدها — رقم الوكالة أو أي بديل.') }}">
                <x-slot:actions>
                    <button type="button" @click="addReference()"
                        class="flex items-center gap-1.5 rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                        <span class="material-symbols-outlined !text-lg">add</span>{{ __('رقم') }}
                    </button>
                </x-slot:actions>

                <div class="space-y-3">
                    <template x-for="(row, index) in references" :key="index">
                        <div class="grid grid-cols-1 gap-3 rounded-2xl border border-outline-variant bg-surface-container-low/40 p-4 sm:grid-cols-12">
                            <div class="space-y-1.5 sm:col-span-3">
                                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'ref-type-' + index">{{ __('النوع') }}</label>
                                <select :id="'ref-type-' + index" :name="'references[' + index + '][type]'" x-model="row.type"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                    <option value="oe">{{ __('رقم الوكالة') }}</option>
                                    <option value="aftermarket">{{ __('رقم بديل') }}</option>
                                </select>
                            </div>

                            <div class="space-y-1.5 sm:col-span-5">
                                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'ref-value-' + index">{{ __('الرقم') }}</label>
                                <input :id="'ref-value-' + index" :name="'references[' + index + '][reference]'" x-model="row.reference" required maxlength="64" dir="ltr"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>

                            <div class="space-y-1.5 sm:col-span-3">
                                <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant" :for="'ref-brand-' + index">{{ __('الماركة') }}</label>
                                <input :id="'ref-brand-' + index" :name="'references[' + index + '][brand]'" x-model="row.brand" maxlength="255"
                                    class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                            </div>

                            <div class="flex items-end sm:col-span-1">
                                <button type="button" @click="removeReference(index)"
                                    class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                    :aria-label="@js(__('حذف السطر'))">
                                    <span class="material-symbols-outlined !text-xl">delete</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </x-card>

            <x-card title="{{ __('الاستبدال والتأمين') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="superseded-by" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('استُبدلت بـ') }}</label>
                        <select id="superseded-by" name="supersededByItemId"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('لا شيء — القطعة ما زالت سارية') }}</option>
                            @foreach ($candidates as $candidate)
                                <option value="{{ $candidate->id }}" @selected($item->superseded_by_item_id === $candidate->id)>{{ $candidate->name }}</option>
                            @endforeach
                        </select>
                        <p class="font-body-sm text-outline">{{ __('البحث برقم القطعة القديمة سيدلّ على الجديدة.') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="core-charge" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تأمين القطعة القديمة') }}</label>
                        <input id="core-charge" type="number" step="0.01" min="0" name="coreCharge" value="{{ (float) $item->core_charge }}" dir="ltr"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        <p class="font-body-sm text-outline">{{ __('مبلغ يُسترد عند إرجاع القطعة التالفة — دينامو، بطارية، مساعدات.') }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-outline-variant/50 pt-5">
                    <a href="{{ route('fitment.index') }}" class="rounded-xl px-5 py-3 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</a>
                    <button type="submit" :disabled="submitting"
                        class="rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                        x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ بيانات الملاءمة'))">{{ __('حفظ بيانات الملاءمة') }}</button>
                </div>
            </x-card>
        </form>
    </div>
@endsection
