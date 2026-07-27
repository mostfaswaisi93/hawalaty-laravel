@extends('layouts.admin')

@section('title', $license->customer_name.' - '.__('حوالاتي'))

@php
    $statusMap = [
        'trial' => ['label' => __('تجريبي'), 'class' => 'bg-secondary/10 text-secondary'],
        'active' => ['label' => __('مُفعّل'), 'class' => 'bg-primary/10 text-primary'],
        'suspended' => ['label' => __('مُعلّق'), 'class' => 'bg-error/10 text-error'],
        'expired' => ['label' => __('منتهي'), 'class' => 'bg-outline/10 text-outline'],
    ];
    $meta = $statusMap[$license->status->value] ?? ['label' => $license->status->value, 'class' => 'bg-outline/10 text-outline'];
    $connection = $license->databaseConnection;
@endphp

@section('content')
    <div class="page-enter space-y-6" x-data="{ confirmOpen: false, confirmMessage: '', confirmFormId: null }">
        <a href="{{ route('admin.licenses.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary" title="{{ __('عودة إلى التراخيص') }}">
            <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>{{ __('عودة إلى التراخيص') }}
        </a>

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="flex items-center gap-3">
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ $license->customer_name }}</h1>
                <span class="rounded-full px-3 py-1 text-body-sm font-bold {{ $meta['class'] }}">{{ $meta['label'] }}</span>
            </div>
            <div class="flex items-center gap-2">
                @if (in_array($license->status->value, ['active', 'trial']))
                    {{-- Confirmed, matching admin.licenses.index: suspending locks every user of the
                         tenant out of the app, so it must never be one unguarded click. --}}
                    <form method="POST" action="{{ route('admin.licenses.suspend', $license->id) }}" id="suspend-{{ $license->id }}">@csrf</form>
                    <button type="button"
                        @click="confirmOpen = true; confirmMessage = '{{ __('تعليق هذا الترخيص؟') }}'; confirmFormId = 'suspend-{{ $license->id }}'; confirmTitle = '{{ __('تأكيد التعليق') }}'; confirmLabel = '{{ __('تعليق') }}'"
                        class="flex items-center gap-2 rounded-xl border border-error/40 px-4 py-2.5 font-body-sm font-bold text-error transition-colors hover:bg-error/10">
                        <span class="material-symbols-outlined !text-xl">pause_circle</span>{{ __('تعليق') }}
                    </button>
                @else
                    <form method="POST" action="{{ route('admin.licenses.activate', $license->id) }}">@csrf
                        <button type="submit" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('تفعيل') }}">
                            <span class="material-symbols-outlined !text-xl">play_circle</span>{{ __('تفعيل') }}
                        </button>
                    </form>
                @endif
            </div>
        </header>

        <div class="stagger grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Overview --}}
            <x-card title="{{ __('بيانات الترخيص') }}" class="lg:col-span-2">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رمز الترخيص') }}</dt>
                        <dd class="mt-1 font-numeral-lg font-bold" dir="ltr">{{ $license->license_code }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الباقة') }}</dt>
                        <dd class="mt-1 font-body-lg">{{ $license->package?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('ينتهي في') }}</dt>
                        <dd class="mt-1 font-body-lg">{{ $license->expired_at?->translatedFormat('j F Y') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رصيد العميل') }}</dt>
                        <dd class="mt-1 font-body-lg font-bold" dir="ltr">{{ number_format($license->customer_balance, 2) }} ₪</dd>
                    </div>
                    @if ($connection)
                        <div>
                            <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قاعدة البيانات') }}</dt>
                            <dd class="mt-1 font-body-lg" dir="ltr">{{ $connection->database_name }}</dd>
                        </div>
                        <div>
                            <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('المضيف') }}</dt>
                            <dd class="mt-1 font-body-lg" dir="ltr">{{ $connection->host }}:{{ $connection->port }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Change package --}}
                <form method="POST" action="{{ route('admin.licenses.change-package', $license->id) }}" class="mt-6 flex flex-col gap-3 border-t border-outline-variant/50 pt-6 sm:flex-row sm:items-end">
                    @csrf
                    <div class="flex-1 space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تغيير الباقة') }}</label>
                        <select name="packageId" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach ($packages as $package)
                                <option value="{{ $package->id }}" @selected($license->package_id === $package->id)>{{ $package->name }} - {{ number_format($package->price, 2) }} ₪</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-xl bg-surface-container px-5 py-3 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high" title="{{ __('حفظ الباقة') }}">{{ __('حفظ الباقة') }}</button>
                </form>
            </x-card>

            {{-- Recent tenant activity --}}
            <x-card title="{{ __('آخر نشاط المستأجر') }}">
                @if ($recentActivity->isEmpty())
                    <p class="py-6 text-center font-body-sm text-on-surface-variant">{{ __('لا يوجد نشاط') }}</p>
                @else
                    <ul class="divide-y divide-outline-variant/50">
                        @foreach ($recentActivity as $log)
                            <li class="py-3">
                                <p class="font-body-sm font-bold">{{ $log->entity_label ?? __($log->action?->value ?? '-') }}</p>
                                <p class="text-[11px] text-on-surface-variant">{{ $log->actor_name ?? '-' }} · {{ $log->created_at?->diffForHumans() }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Team roster --}}
        <x-card title="{{ __('فريق المستأجر') }}" subtitle="{{ __('قراءة مباشرة من قاعدة بيانات المستأجر') }}" class="reveal-up" style="--d: 200ms">
            @if ($team->isEmpty())
                <p class="py-6 text-center font-body-sm text-on-surface-variant">{{ __('لا يوجد أعضاء فريق') }}</p>
            @else
                <x-table-responsive :headers="[__('الاسم'), __('البريد'), __('الدور'), __('الحالة')]">
                    <x-slot:table>
                        @foreach ($team as $member)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm font-bold">{{ $member->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant"><span dir="ltr">{{ $member->email }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">{{ $member->role?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $member->active ? 'bg-primary/10 text-primary' : 'bg-outline/10 text-outline' }}">
                                        {{ $member->active ? __('نشط') : __('معطّل') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($team as $member)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between">
                                    <p class="font-body-sm font-bold">{{ $member->name }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $member->active ? 'bg-primary/10 text-primary' : 'bg-outline/10 text-outline' }}">{{ $member->active ? __('نشط') : __('معطّل') }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant" dir="ltr">{{ $member->email }}</p>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $member->role?->name ?? '-' }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </x-card>

        <x-confirm-modal />
    </div>
@endsection
