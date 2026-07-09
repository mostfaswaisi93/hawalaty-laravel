@extends('layouts.admin')

@section('title', 'لوحة تحكم المنصّة — حوالاتي')

@section('content')
    <div class="page-enter space-y-8">
        <x-card title="لوحة تحكم المنصّة" subtitle="نظرة عامة على التراخيص والمحاسبة الداخلية">
            <p class="text-body-lg text-on-surface-variant">
                مرحباً {{ auth('admin')->user()?->name }} — هذه صفحة مؤقتة تثبت أن غلاف لوحة المشرف (layouts/admin.blade.php)
                يعمل بنجاح. محتوى لوحة التحكم الفعلي يُبنى في مهمة لاحقة.
            </p>
        </x-card>
    </div>
@endsection
