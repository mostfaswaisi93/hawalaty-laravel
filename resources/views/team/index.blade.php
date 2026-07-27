@extends('layouts.app')

@section('title', __('الفريق - حوالاتي'))

@section('content')
    @php
        $roleLabel = fn ($role) => match ($role?->key?->value) {
            'owner' => __('مالك'),
            'manager' => __('مدير'),
            'staff' => __('موظف'),
            default => $role?->name ?? '-',
        };
    @endphp

    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: '{{ old('id') ? 'edit' : 'add' }}',
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
            form: { id: '{{ old('id', '') }}', name: '{{ old('name', '') }}', email: '{{ old('email', '') }}', password: '', roleId: '{{ old('roleId', '') }}', active: {{ old('active', '1') ? 'true' : 'false' }} },
            openAdd() { this.mode = 'add'; this.submitting = false; this.form = { id: '', name: '', email: '', password: '', roleId: '{{ $roles->first()?->id }}', active: true }; this.open = true; },
            openEdit(member) { this.mode = 'edit'; this.submitting = false; this.form = { ...member, password: '' }; this.open = true; },
            payOpen: false, rateOpen: false,
            target: { id: '', name: '', amount: '', period: 'monthly' },
            openPay(m) { this.submitting = false; this.target = { id: m.id, name: m.name, amount: m.amount || '', period: m.period || 'monthly' }; this.payOpen = true; },
            openRate(m) { this.submitting = false; this.target = { id: m.id, name: m.name, amount: m.amount || '', period: m.period || 'monthly' }; this.rateOpen = true; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الفريق') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('ادعُ الموظفين وامنح كلاً منهم الدور المناسب.') }}</p>
            </div>
            <button type="button" @click="openAdd()"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95" title="{{ __('إضافة عضو') }}">
                <span class="material-symbols-outlined !text-xl">person_add</span>
                {{ __('إضافة عضو') }}
            </button>
        </header>

        <form method="GET" action="{{ route('team.index') }}" class="reveal-up relative max-w-md" style="--d:60ms">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 -translate-y-1/2 text-outline {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }}">search</span>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('ابحث بالاسم أو البريد...') }}" aria-label="{{ __('بحث') }}"
                class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary {{ app()->getLocale() === 'ar' ? 'pr-12 pl-4' : 'pl-12 pr-4' }}" />
        </form>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($members->isEmpty())
                <div class="reveal-scale flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">group</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا يوجد أعضاء') }}</p>
                    <button type="button" @click="openAdd()" class="font-body-sm font-bold text-primary hover:underline" title="{{ __('أضف أول عضو') }}">{{ __('أضف أول عضو') }}</button>
                </div>
            @else
                <x-table-responsive :headers="[__('العضو'), __('الدور'), __('الحالة'), ['label' => __('الراتب'), 'align' => 'end'], __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($members as $m)
                            <tr class="reveal-up transition-colors hover:bg-surface-container-low/50" style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">{{ mb_substr($m->name, 0, 1) }}</div>
                                        <div>
                                            <p class="font-body-sm font-bold">{{ $m->name }}</p>
                                            <p class="text-[11px] text-on-surface-variant" dir="ltr">{{ $m->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-full bg-surface-container-high px-3 py-1 text-[11px] font-bold text-on-surface-variant">{{ $roleLabel($m->role) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-[11px] font-bold {{ $m->active ? 'bg-success-container text-success dark:bg-success-container/15' : 'bg-surface-container-high text-outline' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $m->active ? 'bg-green-500' : 'bg-outline' }}"></span>
                                        {{ $m->active ? __('نشط') : __('متوقف') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    @if ($m->hasSalary())
                                        <span class="font-body-sm font-bold text-on-surface"><span dir="ltr">{{ number_format($m->salary_amount, 2) }} ₪</span></span>
                                        <span class="block text-[11px] text-outline">{{ $m->salary_period->label() }}</span>
                                    @else
                                        <span class="text-[11px] text-outline">{{ __('غير محدّد') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="openPay(@js(['id' => $m->id, 'name' => $m->name, 'amount' => $m->salary_amount, 'period' => $m->salary_period?->value]))"
                                            @disabled($banks->isEmpty())
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-success-container/30 hover:text-success disabled:cursor-not-allowed disabled:opacity-40"
                                            aria-label="{{ __('صرف راتب') }}" title="{{ $banks->isEmpty() ? __('أضِف حساباً أولاً') : __('صرف راتب') }}">
                                            <span class="material-symbols-outlined !text-xl">payments</span>
                                        </button>
                                        <button type="button" @click="openRate(@js(['id' => $m->id, 'name' => $m->name, 'amount' => $m->salary_amount, 'period' => $m->salary_period?->value]))"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                                            aria-label="{{ __('تحديد الراتب') }}" title="{{ __('تحديد الراتب') }}">
                                            <span class="material-symbols-outlined !text-xl">request_quote</span>
                                        </button>
                                        <button type="button" @click="openEdit(@js(['id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'roleId' => $m->role_id, 'active' => (bool) $m->active]))"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                            <span class="material-symbols-outlined !text-xl">edit</span>
                                        </button>
                                        <form method="POST" action="{{ route('team.destroy', $m->id) }}" id="delete-team-row-{{ $m->id }}" class="inline-flex">
                                            @csrf @method('DELETE')
                                        </form>
                                        <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}"
                                            @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذا العضو؟') }}'; confirmFormId = 'delete-team-row-{{ $m->id }}'"
                                            class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error">
                                            <span class="material-symbols-outlined !text-xl">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($members as $m)
                            <div class="reveal-up tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4"
                                style="--d:{{ min($loop->index, 11) * 40 + 40 }}ms">
                                <div class="flex items-center gap-3">
                                    <div class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-primary-container font-bold text-on-primary-container">{{ mb_substr($m->name, 0, 1) }}</div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-body-sm font-bold">{{ $m->name }}</p>
                                        <p class="truncate text-[11px] text-on-surface-variant" dir="ltr">{{ $m->email }}</p>
                                    </div>
                                    <span class="rounded-full bg-surface-container-high px-2.5 py-1 text-[11px] font-bold text-on-surface-variant">{{ $roleLabel($m->role) }}</span>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2 border-t border-outline-variant/50 pt-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $m->active ? 'bg-success-container text-success dark:bg-success-container/15' : 'bg-surface-container-high text-outline' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $m->active ? 'bg-green-500' : 'bg-outline' }}"></span>
                                        {{ $m->active ? __('نشط') : __('متوقف') }}
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="openEdit(@js(['id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'roleId' => $m->role_id, 'active' => (bool) $m->active]))"
                                            class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}">
                                            <span class="material-symbols-outlined !text-lg">edit</span>
                                        </button>
                                        <form method="POST" action="{{ route('team.destroy', $m->id) }}" id="delete-team-card-{{ $m->id }}" class="inline-flex">
                                            @csrf @method('DELETE')
                                        </form>
                                        <button type="button" aria-label="{{ __('حذف') }}" title="{{ __('حذف') }}"
                                            @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذا العضو؟') }}'; confirmFormId = 'delete-team-card-{{ $m->id }}'"
                                            class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error">
                                            <span class="material-symbols-outlined !text-lg">delete</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$members" :perPage="$perPage" />
            @endif
        </section>

        {{-- add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false"
                x-init="$watch('open', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                role="dialog" aria-modal="true" aria-labelledby="team-modal-title"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <h3 id="team-modal-title" class="border-b border-outline-variant/60 px-6 py-4 font-headline-md text-headline-md" x-text="mode === 'edit' ? '{{ __('تعديل العضو') }}' : '{{ __('إضافة عضو') }}'"></h3>
                <form :action="mode === 'edit' ? '{{ url('team') }}/' + form.id : '{{ route('team.store') }}'" method="POST" class="space-y-4 p-6" @submit="submitting = true">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="id" :value="form.id">
                    <input type="hidden" name="active" :value="form.active ? 1 : 0">

                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الاسم') }}</label>
                        <input name="name" x-model="form.name" required placeholder="{{ __('اسم الموظف') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('البريد الإلكتروني') }}</label>
                        <input type="email" name="email" x-model="form.email" required dir="ltr" placeholder="name@example.com"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الدور') }}</label>
                            <select name="roleId" x-model="form.roleId" required
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $roleLabel($role) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">
                                {{ __('كلمة المرور') }} <span x-show="mode === 'edit'" class="text-outline">{{ __('(اتركها فارغة)') }}</span>
                            </label>
                            <input type="password" name="password" x-model="form.password" :required="mode === 'add'" dir="ltr" placeholder="••••••"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                        <span class="font-body-sm font-bold text-on-surface">{{ __('نشط') }}</span>
                        <input type="checkbox" x-model="form.active" class="h-5 w-5 rounded accent-primary" />
                    </label>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container" title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : (mode === 'edit' ? '{{ __('حفظ') }}' : '{{ __('إضافة') }}')"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
