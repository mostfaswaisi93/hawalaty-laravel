@extends('layouts.app')

@section('title', __('الإشعارات') . ' - ' . __('حوالاتي'))

@section('content')
    @php

        $money = fn ($v) => number_format((float) $v, 2) . ' ₪';

        $meta = function (array $data) {
            $kind = $data['kind'] ?? '';
            if ($kind === 'new_order') {
                return [
                    'icon' => 'receipt_long',
                    'chip' => 'bg-primary/10 text-primary',
                    'title' => __('طلب جديد من') . ' ' . ($data['customer'] ?? ''),
                ];
            }

            $status = $data['status'] ?? null;
            $completed = $status === 'completed';

            return [
                'icon' => $completed ? 'check_circle' : 'error',
                'chip' => $completed
                    ? 'bg-success-container text-success dark:bg-success-container/15'
                    : 'bg-error-container text-error dark:bg-error/15',
                'title' => __('تغيّرت حالة طلب') . ' ' . ($data['customer'] ?? ''),
            ];
        };

        $statusLabel = fn ($status) => match ($status) {
            'completed' => __('مستلمة'),
            'pending' => __('قيد الانتظار'),
            'failed' => __('فاشلة'),
            default => null,
        };
        $statusChip = fn ($status) => match ($status) {
            'completed' => 'bg-success-container text-success dark:bg-success-container/15',
            'pending' => 'bg-secondary-container/20 text-secondary',
            default => 'bg-error-container text-error dark:bg-error/15',
        };
    @endphp

    <div class="page-enter space-y-6">
        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الإشعارات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">
                    {{ $notifications->total() }} {{ __('إشعار') }}
                    @if ($unreadCount > 0)
                        · <span class="font-bold text-primary">{{ $unreadCount }} {{ __('غير مقروء') }}</span>
                    @endif
                </p>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <button type="submit" :disabled="submitting" class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary disabled:cursor-not-allowed disabled:opacity-60" title="{{ __('تعليم الكل كمقروء') }}">
                        <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                            x-text="submitting ? 'progress_activity' : 'done_all'">done_all</span>
                        <span x-text="submitting ? @js(__('جارٍ التعليم...')) : @js(__('تعليم الكل كمقروء'))">{{ __('تعليم الكل كمقروء') }}</span>
                    </button>
                </form>
            @endif
        </header>

        <section class="reveal-up tonal-elevation overflow-hidden rounded-3xl border border-outline-variant/50 bg-surface-container-lowest" style="--d: 100ms">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $m = $meta($data);
                    $unread = $notification->read_at === null;
                    $transferId = $data['transferId'] ?? null;
                    $status = $data['status'] ?? null;
                @endphp
                <div class="flex items-start gap-4 border-b border-outline-variant/40 p-4 transition-colors last:border-b-0 hover:bg-surface-container-low {{ $unread ? 'bg-primary/5' : '' }}">
                    <span class="mt-0.5 flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full {{ $m['chip'] }}">
                        <span class="material-symbols-outlined !text-xl">{{ $m['icon'] }}</span>
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="font-body-lg font-bold text-on-surface">{{ $m['title'] }}</p>
                            @if ($status && $statusLabel($status))
                                <span class="rounded-full px-2 py-0.5 font-label-caps text-label-caps {{ $statusChip($status) }}">{{ $statusLabel($status) }}</span>
                            @endif
                            @if ($unread)
                                <span class="h-2 w-2 flex-shrink-0 rounded-full bg-primary" title="{{ __('غير مقروء') }}"></span>
                            @endif
                        </div>

                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 font-body-sm text-on-surface-variant">
                            @isset($data['amount'])
                                <span dir="ltr" class="font-bold text-on-surface">{{ $money($data['amount']) }}</span>
                            @endisset
                            @isset($data['itemName'])
                                <span class="truncate">{{ $data['itemName'] }}</span>
                            @endisset
                        </div>

                        <div class="mt-2 flex items-center gap-3">
                            <span class="font-body-sm text-body-sm text-outline" title="{{ $notification->created_at->format('Y-m-d H:i') }}">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                            @if ($transferId)
                                <a href="{{ route('transfers.receipt', $transferId) }}" class="font-body-sm text-body-sm font-bold text-primary transition-colors hover:text-primary-container">
                                    {{ __('عرض الطلب') }}
                                </a>
                            @endif
                            @if ($unread)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                    @csrf
                                    <button type="submit" :disabled="submitting"
                                        class="font-body-sm text-body-sm font-bold text-on-surface-variant transition-colors hover:text-primary disabled:cursor-not-allowed disabled:opacity-60"
                                        x-text="submitting ? @js(__('جارٍ التعليم...')) : @js(__('تعليم كمقروء'))">{{ __('تعليم كمقروء') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="reveal-scale flex flex-col items-center gap-3 py-20 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">notifications_off</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد إشعارات بعد') }}</p>
                </div>
            @endforelse
        </section>

        @if ($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
