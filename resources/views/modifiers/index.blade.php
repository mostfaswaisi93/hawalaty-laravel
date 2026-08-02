@extends('layouts.app')

@section('title', __('الإضافات والخيارات - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            groupOpen: {{ $errors->any() ? 'true' : 'false' }},
            groupMode: 'add',
            optionOpen: false,
            linkOpen: false,
            submitting: false,
            group: { id: '', name: '', selection: 'single', isRequired: false, minSelect: 0, maxSelect: '', position: 0, active: true },
            option: { id: '', groupId: '', name: '', priceDelta: '', componentItemId: '', componentQuantity: '', isDefault: false, position: 0, active: true },
            link: { itemId: '', groupIds: [] },
            openAddGroup() { this.groupMode = 'add'; this.submitting = false; this.group = { id: '', name: '', selection: 'single', isRequired: false, minSelect: 0, maxSelect: '', position: 0, active: true }; this.groupOpen = true },
            openEditGroup(g) { this.groupMode = 'edit'; this.submitting = false; this.group = { ...g }; this.groupOpen = true },
            openAddOption(groupId) { this.submitting = false; this.option = { id: '', groupId, name: '', priceDelta: '', componentItemId: '', componentQuantity: '', isDefault: false, position: 0, active: true }; this.optionOpen = true },
            openEditOption(o) { this.submitting = false; this.option = { ...o }; this.optionOpen = true },
            openLink(itemId, groupIds) { this.submitting = false; this.link = { itemId, groupIds: [...groupIds] }; this.linkOpen = true },
            get groupAction() { return this.groupMode === 'edit' ? '/modifiers/groups/' + this.group.id : '{{ route('modifiers.groups.store') }}' },
            get optionAction() { return this.option.id ? '/modifiers/options/' + this.option.id : '/modifiers/groups/' + this.option.groupId + '/options' },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الإضافات والخيارات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('ما يختاره الزبون وقت الطلب: الحجم، الإضافات، درجة الحرارة.') }}</p>
            </div>
            <button type="button" @click="openAddGroup()"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('مجموعة خيارات') }}
            </button>
        </header>

        @if ($groups->isEmpty())
            <div class="reveal-scale flex flex-col items-center gap-3 rounded-3xl border border-outline-variant/50 bg-surface-container-lowest py-16 text-center">
                <span class="material-symbols-outlined !text-5xl text-outline/50">tune</span>
                <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد مجموعات خيارات بعد') }}</p>
                <p class="max-w-md font-body-sm text-outline">{{ __('مثال: مجموعة «الحجم» باختيار واحد إلزامي، ومجموعة «إضافات» باختيار متعدد.') }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($groups as $group)
                    @php
                        $groupPayload = [
                            'id' => $group->id, 'name' => $group->name, 'selection' => $group->selection,
                            'isRequired' => (bool) $group->is_required, 'minSelect' => (int) $group->min_select,
                            'maxSelect' => $group->max_select ?? '', 'position' => (int) $group->position, 'active' => (bool) $group->active,
                        ];
                    @endphp
                    <x-card class="reveal-up" style="--d:{{ min($loop->index, 11) * 40 + 60 }}ms">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-headline-md text-headline-md">{{ $group->name }}</h3>
                                    <span class="rounded-lg bg-surface-container px-2 py-1 font-body-sm text-on-surface-variant">{{ $group->selectionLabel() }}</span>
                                    @if ($group->is_required)
                                        <span class="rounded-lg bg-secondary-container/40 px-2 py-1 font-body-sm text-secondary">{{ __('إلزامي') }}</span>
                                    @endif
                                    @unless ($group->active)
                                        <span class="rounded-lg bg-error/10 px-2 py-1 font-body-sm text-error">{{ __('غير مفعّل') }}</span>
                                    @endunless
                                </div>
                                <p class="mt-1 font-body-sm text-outline">
                                    {{ __('مرتبطة بـ :count صنف', ['count' => $group->items_count]) }}
                                </p>
                            </div>

                            <div class="flex flex-shrink-0 items-center gap-1">
                                <button type="button" @click="openAddOption(@js($group->id))"
                                    class="flex items-center gap-1.5 rounded-xl bg-surface-container px-3 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                                    <span class="material-symbols-outlined !text-lg">add</span>{{ __('خيار') }}
                                </button>
                                <button type="button" @click="openEditGroup({{ \Illuminate\Support\Js::from($groupPayload) }})"
                                    class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                    aria-label="{{ __('تعديل :name', ['name' => $group->name]) }}">
                                    <span class="material-symbols-outlined !text-xl">edit</span>
                                </button>
                                <form method="POST" action="{{ route('modifiers.groups.destroy', $group->id) }}"
                                    @submit.prevent="if (confirm('{{ __('حذف المجموعة وكل خياراتها؟') }}')) $el.submit()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                        aria-label="{{ __('حذف :name', ['name' => $group->name]) }}">
                                        <span class="material-symbols-outlined !text-xl">delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($group->modifiers->isEmpty())
                            <p class="mt-4 rounded-xl bg-surface-container-low p-4 font-body-sm text-on-surface-variant">{{ __('لا توجد خيارات في هذه المجموعة بعد.') }}</p>
                        @else
                            <ul class="mt-4 divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                                @foreach ($group->modifiers as $modifier)
                                    @php
                                        $optionPayload = [
                                            'id' => $modifier->id, 'groupId' => $group->id, 'name' => $modifier->name,
                                            'priceDelta' => (float) $modifier->price_delta,
                                            'componentItemId' => $modifier->component_item_id ?? '',
                                            'componentQuantity' => $modifier->component_quantity !== null ? (float) $modifier->component_quantity : '',
                                            'isDefault' => (bool) $modifier->is_default, 'position' => (int) $modifier->position, 'active' => (bool) $modifier->active,
                                        ];
                                    @endphp
                                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="font-body-lg font-bold">
                                                {{ $modifier->name }}
                                                @if ($modifier->is_default)
                                                    <span class="ms-1 font-body-sm text-outline">({{ __('افتراضي') }})</span>
                                                @endif
                                            </p>
                                            @if ($modifier->consumesStock())
                                                <p class="font-body-sm text-outline">
                                                    {{ __('يخصم') }} {{ rtrim(rtrim(number_format((float) $modifier->component_quantity, 4), '0'), '.') }}
                                                    {{ $modifier->component?->unit()->shortLabel() }} {{ __('من') }} {{ $modifier->component?->name }}
                                                </p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-numeral-lg font-bold {{ (float) $modifier->price_delta < 0 ? 'text-error' : '' }}" dir="ltr">
                                                {{ (float) $modifier->price_delta > 0 ? '+' : '' }}{{ number_format((float) $modifier->price_delta, 3) }}
                                            </span>
                                            <button type="button" @click="openEditOption({{ \Illuminate\Support\Js::from($optionPayload) }})"
                                                class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                                                aria-label="{{ __('تعديل :name', ['name' => $modifier->name]) }}">
                                                <span class="material-symbols-outlined !text-xl">edit</span>
                                            </button>
                                            <form method="POST" action="{{ route('modifiers.options.destroy', $modifier->id) }}"
                                                @submit.prevent="if (confirm('{{ __('حذف هذا الخيار؟') }}')) $el.submit()">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                                    aria-label="{{ __('حذف :name', ['name' => $modifier->name]) }}">
                                                    <span class="material-symbols-outlined !text-xl">delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                @endforeach
            </div>
        @endif

        <x-card title="{{ __('ربط الخيارات بالأصناف') }}" subtitle="{{ __('المجموعة الواحدة تُستخدم على عدة أصناف — «الحجم» تصلح لكل المشروبات.') }}">
            @if ($items->isEmpty())
                <p class="py-6 text-center font-body-sm text-on-surface-variant">{{ __('لا توجد أصناف بعد') }}</p>
            @else
                <ul class="divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                    @foreach ($items as $item)
                        @php $linked = $item->modifierGroups->pluck('id')->all(); @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="font-body-lg font-bold">{{ $item->name }}</p>
                                <p class="font-body-sm text-outline">
                                    {{ $item->modifierGroups->isEmpty() ? __('بلا خيارات') : $item->modifierGroups->pluck('name')->implode('، ') }}
                                </p>
                            </div>
                            <button type="button" @click="openLink(@js($item->id), {{ \Illuminate\Support\Js::from($linked) }})"
                                class="rounded-xl bg-surface-container px-4 py-2 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                                {{ __('ربط') }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @include('modifiers._dialogs')
    </div>
@endsection
