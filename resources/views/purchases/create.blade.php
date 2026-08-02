@extends('layouts.app')

@section('title', __('أمر شراء جديد - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <a href="{{ route('purchases.index') }}" class="inline-flex items-center gap-1 font-body-sm text-on-surface-variant transition-colors hover:text-primary">
                <span class="material-symbols-outlined !text-lg rtl:-scale-x-100">arrow_back</span>
                {{ __('المشتريات') }}
            </a>
            <h1 class="mt-1 font-display-lg text-display-lg text-on-surface">{{ __('أمر شراء جديد') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('يُحفَظ كمسودة أولًا. الكميات لا تدخل المخزون إلا عند الاستلام.') }}</p>
        </header>

        @include('purchases._form', [
            'action' => route('purchases.store'),
            'method' => 'POST',
            'submitLabel' => __('حفظ كمسودة'),
            'purchase' => null,
        ])
    </div>
@endsection
