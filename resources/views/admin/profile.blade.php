@extends('layouts.admin')

@section('title', __('الملف الشخصي - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6" x-data="{ submitting: false }">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الملف الشخصي') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('بيانات حسابك وكلمة المرور.') }}</p>
        </header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card class="lg:col-span-2" title="{{ __('بياناتي') }}" subtitle="{{ __('الاسم والبريد الإلكتروني وكلمة المرور') }}">
                <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-5" @submit="submitting = true">
                    @csrf
                    @method('PUT')

                    <x-form-field
                        name="name"
                        label="{{ __('الاسم') }}"
                        icon="person"
                        :value="old('name', $admin->name)" />

                    <x-form-field
                        name="email"
                        type="email"
                        label="{{ __('البريد الإلكتروني') }}"
                        icon="mail"
                        dir="ltr"
                        :value="old('email', $admin->email)" />

                    <div class="border-t border-outline-variant pt-5">
                        <p class="mb-4 font-body-sm text-on-surface-variant">
                            {{ __('لتغيير كلمة المرور، املأ الحقلين أدناه — وإلا اتركهما فارغين.') }}
                        </p>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <x-form-field
                                name="password"
                                type="password"
                                label="{{ __('كلمة المرور الجديدة') }}"
                                icon="lock"
                                dir="ltr"
                                autocomplete="new-password" />

                            <x-form-field
                                name="password_confirmation"
                                type="password"
                                label="{{ __('تأكيد كلمة المرور') }}"
                                icon="lock"
                                dir="ltr"
                                autocomplete="new-password" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="submitting"
                            class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50" title="{{ __('حفظ التغييرات') }}">
                            <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                                x-text="submitting ? 'progress_activity' : 'save'">save</span>
                            <span x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ التغييرات'))">{{ __('حفظ التغييرات') }}</span>
                        </button>
                    </div>
                </form>
            </x-card>

            <x-card title="{{ __('الحساب') }}">
                <div class="flex flex-col items-center gap-3 py-4 text-center">
                    <div class="grid h-20 w-20 place-items-center rounded-full bg-primary-container font-display-lg text-3xl font-bold text-on-primary-container">
                        {{ mb_substr($admin->name, 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-headline-md text-headline-md text-on-surface">{{ $admin->name }}</p>
                        <p class="truncate font-body-sm text-on-surface-variant" dir="ltr">{{ $admin->email }}</p>
                    </div>
                    <span class="rounded-full bg-primary/10 px-3 py-1 font-body-sm text-[11px] font-bold text-primary">{{ __('المشرف العام') }}</span>
                </div>

                <dl class="mt-2 space-y-3 border-t border-outline-variant pt-4">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-body-sm text-on-surface-variant">{{ __('تاريخ الانضمام') }}</dt>
                        <dd class="font-body-sm font-bold text-on-surface">{{ $admin->created_at?->translatedFormat('j F Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
