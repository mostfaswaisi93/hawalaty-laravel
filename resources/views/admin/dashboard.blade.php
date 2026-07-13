@extends('layouts.admin')

@section('title', __('لوحة تحكم المنصّة — حوالاتي'))

@section('content')
    <div class="page-enter space-y-8">
        <x-card title="{{ __('لوحة تحكم المنصّة') }}" subtitle="{{ __('نظرة عامة على التراخيص والمحاسبة الداخلية') }}">
            <p class="text-body-lg text-on-surface-variant">
                {{ __('مرحباً') }} {{ auth('admin')->user()?->name }} — {{ __('هذه صفحة مؤقتة تثبت أن غلاف لوحة المشرف') }} (layouts/admin.blade.php)
                {{ __('يعمل بنجاح. محتوى لوحة التحكم الفعلي يُبنى في مهمة لاحقة.') }}
            </p>
        </x-card>
    </div>
@endsection
