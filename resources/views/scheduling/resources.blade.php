@extends('layouts.app')

@section('title', __('الموارد - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, submitting: false }">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <a href="{{ route('scheduling.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                    {{ __('المواعيد') }}
                </a>
                <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('الموارد') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('ما يحتاجه العمل ولا يوجد منه إلا واحد: فني، مكان عمل، معدّة.') }}</p>
            </div>
            <button type="button" @click="open = true"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>
                {{ __('مورد جديد') }}
            </button>
        </header>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($resources->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">groups</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد موارد بعد') }}</p>
                </div>
            @else
                <ul class="divide-y divide-outline-variant/60 rounded-2xl border border-outline-variant">
                    @foreach ($resources as $resource)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="font-body-lg font-bold">{{ $resource->name }}</p>
                                <p class="font-body-sm text-outline">
                                    {{ $resource->kindLabel() }}
                                    @if ($resource->teamMember)
                                        — {{ $resource->teamMember->name }}
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('scheduling.resources.update', $resource->id) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="active" value="0" />
                                <input id="resource-active-{{ $resource->id }}" type="checkbox" name="active" value="1" @checked($resource->active)
                                    onchange="this.form.submit()"
                                    class="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                                <label for="resource-active-{{ $resource->id }}" class="font-body-sm">{{ __('مفعّل') }}</label>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="resource-title">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                x-show="open" @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

                <x-modal-close on-close="open = false" />

                <div class="border-b border-outline-variant/60 py-4 ps-6 pe-14">
                    <h3 id="resource-title" class="font-headline-md text-headline-md">{{ __('مورد جديد') }}</h3>
                </div>

                <form method="POST" action="{{ route('scheduling.resources.store') }}" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="resource-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input id="resource-name" name="name" value="{{ old('name') }}" required maxlength="255"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('name')<p class="font-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="resource-kind" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('النوع') }}</label>
                        <select id="resource-kind" name="kind" required
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach ($kinds as $value => $label)
                                <option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="resource-member" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('عضو الفريق (اختياري)') }}</label>
                        <select id="resource-member" name="teamMemberId"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('غير مرتبط') }}</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected(old('teamMemberId') === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                        <p class="font-body-sm text-outline">{{ __('اربطه بعضو الفريق ليبقى اسمه ودفتر مواعيده شيئًا واحدًا.') }}</p>
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
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
