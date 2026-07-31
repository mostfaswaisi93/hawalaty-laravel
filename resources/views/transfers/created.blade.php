@extends('layouts.app')

@section('title', __('تم إنشاء الطلب - حوالاتي'))

@php
    $symbol = $order->account?->currency?->symbol ?? '₪';
@endphp

@section('content')
    {{--
        Where a freshly created order lands. The two things an operator does next
        are print the invoice and start the next order, so those are the only two
        buttons - everything else is a quiet link underneath.
    --}}
    <style>
        /* The tick is DRAWN rather than faded in - the stroke arriving is what
           makes the moment read as "done", and it costs one keyframe. */
        @keyframes ok-pop { 0% { transform: scale(.4); opacity: 0 } 60% { transform: scale(1.08) } 100% { transform: scale(1); opacity: 1 } }
        @keyframes ok-draw { to { stroke-dashoffset: 0 } }
        @keyframes ok-ring { 0% { transform: scale(.8); opacity: .55 } 100% { transform: scale(1.9); opacity: 0 } }

        .ok-badge { animation: ok-pop .45s cubic-bezier(.2, .9, .3, 1.4) both; }
        .ok-ring { animation: ok-ring 1.1s ease-out .2s both; }
        .ok-tick { stroke-dasharray: 32; stroke-dashoffset: 32; animation: ok-draw .4s ease-out .35s forwards; }

        /* Someone who asked for less motion gets the end state, not the journey. */
        @media (prefers-reduced-motion: reduce) {
            .ok-badge, .ok-ring, .ok-tick { animation: none; }
            .ok-tick { stroke-dashoffset: 0; }
            .ok-ring { opacity: 0; }
        }
    </style>

    <div class="page-enter mx-auto max-w-lg">
        <div class="reveal-scale tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6 text-center md:p-8">
            <span class="ok-badge relative mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-success-container text-success dark:bg-success-container/20">
                <span class="ok-ring absolute inset-0 rounded-full ring-2 ring-success"></span>
                <svg viewBox="0 0 24 24" class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path class="ok-tick" d="M4.5 12.5 L9.5 17.5 L19.5 7" />
                </svg>
            </span>

            <h1 class="reveal-up mt-4 font-display-lg text-2xl font-bold text-on-surface md:text-display-lg" style="--d:120ms">{{ __('تم إنشاء الطلب') }}</h1>
            <p class="reveal-up mt-1 font-body-lg text-on-surface-variant" style="--d:180ms">{{ __('أُنشئت الفاتورة وسُجّلت في النظام.') }}</p>

            {{-- The receipt's own identity, so the operator can match paper to screen. --}}
            <dl class="reveal-up mt-6 space-y-2 rounded-2xl border border-outline-variant/60 bg-surface-container-low p-4 text-start" style="--d:240ms">
                <div class="flex items-center justify-between gap-3">
                    <dt class="font-body-sm text-on-surface-variant">{{ __('رقم الفاتورة') }}</dt>
                    <dd class="font-numeral-lg font-bold text-on-surface" dir="ltr">#{{ strtoupper(substr($order->id, -8)) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="font-body-sm text-on-surface-variant">{{ __('العميل') }}</dt>
                    <dd class="truncate font-bold text-on-surface">{{ $order->customer }}</dd>
                </div>
                @if ($order->reference_number)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-body-sm text-on-surface-variant">{{ __('الرقم المرجعي') }}</dt>
                        <dd class="font-numeral-lg font-bold text-on-surface" dir="ltr">{{ $order->reference_number }}</dd>
                    </div>
                @endif
                @if ($order->discount > 0)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-body-sm text-on-surface-variant">{{ __('الخصم') }}</dt>
                        <dd class="font-bold text-error"><x-money :amount="$order->discount" :symbol="$symbol" /></dd>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-3 border-t border-outline-variant/60 pt-2">
                    <dt class="font-body-lg font-bold text-on-surface">{{ __('الإجمالي') }}</dt>
                    <dd class="font-display-lg text-xl font-bold text-primary"><x-money :amount="$order->amount" :symbol="$symbol" /></dd>
                </div>
            </dl>

            <div class="reveal-up mt-6 grid gap-3 sm:grid-cols-2" style="--d:320ms">
                {{-- New tab: the print dialog must not take the operator off this
                     page, or the "next order" button goes with it. --}}
                <a href="{{ route('transfers.receipt', $order->id) }}" target="_blank"
                    class="flex items-center justify-center gap-2 rounded-xl border border-primary bg-surface-container-lowest px-5 py-3.5 font-body-lg font-bold text-primary transition-colors hover:bg-primary/10"
                    title="{{ __('طباعة الفاتورة') }}">
                    <span class="material-symbols-outlined">print</span>
                    {{ __('طباعة الفاتورة') }}
                </a>
                <a href="{{ route('transfers.index') }}"
                    class="flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3.5 font-body-lg font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-[0.98]"
                    title="{{ __('طلب جديد') }}">
                    <span class="material-symbols-outlined">add</span>
                    {{ __('طلب جديد') }}
                </a>
            </div>

            <a href="{{ route('history.index') }}" class="mt-4 inline-block font-body-sm font-bold text-on-surface-variant hover:text-primary hover:underline">
                {{ __('عرض السجل') }}
            </a>
        </div>
    </div>

    <script>
        (function () {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;

            /**
             * A till: the drawer's short knock, then the bell.
             *
             * The bell is what makes it read as MONEY rather than as a generic
             * "done" beep. Its partials are deliberately INHARMONIC (2.76x, 5.4x
             * - not 2x, 3x): whole-number ratios sound like an organ, and it is
             * the out-of-tune ones that the ear hears as struck metal.
             */
            const chime = () => {
                const ctx = new Ctx();
                const now = ctx.currentTime;
                const out = ctx.createGain();
                out.gain.value = 0.5;
                out.connect(ctx.destination);

                // ── the drawer: a filtered noise knock ──
                const len = Math.floor(ctx.sampleRate * 0.05);
                const buf = ctx.createBuffer(1, len, ctx.sampleRate);
                const data = buf.getChannelData(0);
                for (let i = 0; i < len; i++) {
                    // Decaying white noise - a flat burst reads as static, not a knock.
                    data[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 2.5);
                }
                const noise = ctx.createBufferSource();
                noise.buffer = buf;
                const knockFilter = ctx.createBiquadFilter();
                knockFilter.type = 'bandpass';
                knockFilter.frequency.value = 1400;
                knockFilter.Q.value = 0.8;
                const knockGain = ctx.createGain();
                knockGain.gain.value = 0.35;
                noise.connect(knockFilter).connect(knockGain).connect(out);
                noise.start(now);

                // ── the bell, struck just after the knock ──
                const strike = now + 0.045;
                const base = 1244.5;                       // D#6 - bright, cuts through a noisy shop
                [[1, 0.9, 1.1], [2.0, 0.5, 0.85], [2.76, 0.32, 0.7], [5.4, 0.14, 0.45]]
                    .forEach(([ratio, level, decay]) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = base * ratio;
                        gain.gain.setValueAtTime(0.0001, strike);
                        gain.gain.exponentialRampToValueAtTime(level * 0.22, strike + 0.006);
                        gain.gain.exponentialRampToValueAtTime(0.0001, strike + decay);
                        osc.connect(gain).connect(out);
                        osc.start(strike);
                        osc.stop(strike + decay + 0.05);
                    });

                // A second, quieter strike a beat later - the classic two-hit "cha-ching".
                const second = strike + 0.13;
                [[1, 0.7, 0.9], [2.0, 0.38, 0.7], [2.76, 0.22, 0.55]]
                    .forEach(([ratio, level, decay]) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = base * 1.5 * ratio;   // a fifth up
                        gain.gain.setValueAtTime(0.0001, second);
                        gain.gain.exponentialRampToValueAtTime(level * 0.2, second + 0.006);
                        gain.gain.exponentialRampToValueAtTime(0.0001, second + decay);
                        osc.connect(gain).connect(out);
                        osc.start(second);
                        osc.stop(second + decay + 0.05);
                    });

                setTimeout(() => ctx.close(), 1600);
            };
            try {
                const ctx = new Ctx();
                if (ctx.state === 'suspended') {
                    // Autoplay policy: a fresh navigation carries no user gesture,
                    // so wait for the next real interaction rather than failing.
                    ctx.close();
                    const once = () => { chime(); window.removeEventListener('pointerdown', once); };
                    window.addEventListener('pointerdown', once, { once: true });
                } else {
                    ctx.close();
                    chime();
                }
            } catch (e) { /* audio unavailable - the page is still correct without it */ }
        })();
    </script>
@endsection
