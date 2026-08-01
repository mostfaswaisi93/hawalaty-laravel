@extends('layouts.admin')

@section('title', __('رسائل التواصل - حوالاتي'))

@php
    // The form stores a slug so the value is stable across locales; the label is
    // resolved for display only.
    $subjectLabels = [
        'sales' => __('استفسار عن الأسعار'),
        'demo' => __('طلب عرض توضيحي'),
        'support' => __('دعم فني'),
        'partnership' => __('شراكة'),
        'other' => __('موضوع آخر'),
    ];
@endphp

@section('content')
    <div class="page-enter space-y-6"
        x-data="{ confirmOpen: false, confirmMessage: '', confirmFormId: null }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('رسائل التواصل') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('الرسائل الواردة من نموذج التواصل في الصفحة الرئيسية.') }}</p>
            </div>
            @if ($unreadCount > 0)
                <span class="flex items-center gap-2 rounded-full bg-secondary-container/20 px-4 py-2 font-body-sm font-bold text-secondary">
                    <span class="material-symbols-outlined !text-xl" data-weight="fill">mark_email_unread</span>
                    {{ trans_choice('{1} رسالة واحدة غير مقروءة|[2,*] :count رسائل غير مقروءة', $unreadCount, ['count' => $unreadCount]) }}
                </span>
            @endif
        </header>

        <section class="tonal-elevation reveal-up rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6" style="--d: 80ms">
            @if ($messages->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center" style="--d: 240ms">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">mail</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا توجد رسائل بعد') }}</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($messages as $message)
                        <article class="rounded-2xl border p-5 transition-colors {{ $message->isUnread() ? 'border-secondary-container/60 bg-secondary-container/5' : 'border-outline-variant/60 bg-surface-container-lowest' }}">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="font-headline-md text-body-lg font-bold text-on-surface">{{ $message->name }}</h2>
                                        @if ($message->isUnread())
                                            <span class="rounded-full bg-secondary-container px-2.5 py-0.5 font-label-caps text-label-caps font-bold uppercase tracking-wider text-on-secondary-fixed">{{ __('جديدة') }}</span>
                                        @endif
                                        <span class="rounded-full bg-primary/10 px-2.5 py-0.5 font-body-sm text-primary">{{ $subjectLabels[$message->subject] ?? $message->subject }}</span>
                                        @if ($message->plan)
                                            <span class="rounded-full bg-surface-container-high px-2.5 py-0.5 font-body-sm text-on-surface-variant">{{ $message->plan }}</span>
                                        @endif
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 font-body-sm text-on-surface-variant">
                                        <a href="mailto:{{ $message->email }}" class="flex items-center gap-1.5 transition-colors hover:text-primary">
                                            <span class="material-symbols-outlined !text-base">mail</span><span dir="ltr">{{ $message->email }}</span>
                                        </a>
                                        @if ($message->phone)
                                            <a href="tel:{{ $message->phone }}" class="flex items-center gap-1.5 transition-colors hover:text-primary">
                                                <span class="material-symbols-outlined !text-base">call</span><span dir="ltr">{{ $message->phone }}</span>
                                            </a>
                                        @endif
                                        @if ($message->company)
                                            <span class="flex items-center gap-1.5">
                                                <span class="material-symbols-outlined !text-base">storefront</span>{{ $message->company }}
                                            </span>
                                        @endif
                                        <span class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined !text-base">schedule</span>
                                            <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->diffForHumans() }}</time>
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-shrink-0 items-center gap-1">
                                    @if ($message->isUnread())
                                        {{-- Scoped x-data per row: each message's button
                                             tracks its own in-flight state. --}}
                                        <form method="POST" action="{{ route('admin.contact-messages.read', $message->id) }}"
                                            x-data="{ submitting: false }" @submit="submitting = true">
                                            @csrf
                                            <button type="submit" :disabled="submitting" aria-label="{{ __('تعليم كمقروءة') }}" title="{{ __('تعليم كمقروءة') }}"
                                                @mouseenter="showTipBelow($el, @js(__('تعليم كمقروءة')))" @mouseleave="hideTip()"
                                                @focus="showTipBelow($el, @js(__('تعليم كمقروءة')))" @blur="hideTip()"
                                                class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary disabled:cursor-not-allowed disabled:opacity-60">
                                                <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                                                    x-text="submitting ? 'progress_activity' : 'mark_email_read'">mark_email_read</span>
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.contact-messages.destroy', $message->id) }}" id="del-contact-{{ $message->id }}">@csrf @method('DELETE')</form>
                                    <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}"
                                        @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذه الرسالة؟') }}'; confirmFormId = 'del-contact-{{ $message->id }}'"
                                        class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                        <span class="material-symbols-outlined !text-xl">delete</span>
                                    </button>
                                </div>
                            </div>

                            <p class="mt-4 whitespace-pre-line font-body-sm leading-relaxed text-on-surface">{{ $message->message }}</p>
                        </article>
                    @endforeach
                </div>

                <x-table-footer :paginator="$messages" :perPage="$perPage" />
            @endif
        </section>

        <x-confirm-modal />
    </div>
@endsection
