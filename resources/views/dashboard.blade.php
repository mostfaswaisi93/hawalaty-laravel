@extends('layouts.app')

@section('title', 'لوحة التحكم — حوالاتي')

@section('content')
    <div class="page-enter space-y-8">
        <x-card title="لوحة التحكم" subtitle="نظرة عامة على نشاط حوالاتك">
            <p class="text-body-lg text-on-surface-variant">
                مرحباً {{ auth('tenant')->user()?->name }} — هذه صفحة مؤقتة تثبت أن غلاف التطبيق (layouts/app.blade.php)
                يعمل بنجاح لمستخدم مسجّل دخوله على قاعدة بيانات عميله الصحيحة. محتوى لوحة التحكم الفعلي يُبنى في مهمة لاحقة.
            </p>
        </x-card>
    </div>
@endsection
