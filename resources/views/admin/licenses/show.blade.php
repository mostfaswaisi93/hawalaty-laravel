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

    $actionLabels = [
        'created' => __('إنشاء'),
        'updated' => __('تعديل'),
        'deleted' => __('حذف'),
        'loggedIn' => __('تسجيل دخول'),
        'signedOut' => __('تسجيل خروج'),
    ];
    $actionPill = fn (?string $value) => match ($value) {
        'created' => 'bg-primary/10 text-primary',
        'deleted' => 'bg-error/10 text-error',
        'updated' => 'bg-secondary/10 text-secondary',
        default => 'bg-surface-container-high text-on-surface-variant',
    };
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
                    <form method="POST" action="{{ route('admin.licenses.suspend', $license->id) }}" id="suspend-{{ $license->id }}">@csrf</form>
                    <button type="button"
                        @click="confirmOpen = true; confirmMessage = '{{ __('تعليق هذا الترخيص؟') }}'; confirmFormId = 'suspend-{{ $license->id }}'; confirmTitle = '{{ __('تأكيد التعليق') }}'; confirmLabel = '{{ __('تعليق') }}'"
                        class="flex items-center gap-2 rounded-xl border border-error/40 px-4 py-2.5 font-body-sm font-bold text-error transition-colors hover:bg-error/10">
                        <span class="material-symbols-outlined !text-xl">pause_circle</span>{{ __('تعليق') }}
                    </button>
                @else
                    <form method="POST" action="{{ route('admin.licenses.activate', $license->id) }}" x-data="{ submitting: false }" @submit="submitting = true">@csrf
                        <button type="submit" :disabled="submitting" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60" title="{{ __('تفعيل') }}">
                            <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                                x-text="submitting ? 'progress_activity' : 'play_circle'">play_circle</span>
                            <span x-text="submitting ? @js(__('جارٍ التفعيل...')) : @js(__('تفعيل'))">{{ __('تفعيل') }}</span>
                        </button>
                    </form>
                @endif
            </div>
        </header>

        <div class="stagger grid grid-cols-1 gap-6 lg:grid-cols-3">
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
                        <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تصنيف النشاط') }}</dt>
                        <dd class="mt-1 flex items-center gap-1.5 font-body-lg">
                            <span class="material-symbols-outlined !text-xl text-primary">{{ $license->businessType()->icon() }}</span>
                            {{ $license->businessType()->label() }}
                        </dd>
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
                        <div>
                            <dt class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('مستخدم قاعدة البيانات') }}</dt>
                            <dd class="mt-1 font-body-lg" dir="ltr">{{ $connection->username }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($connection)
                    <div class="mt-6 flex flex-col gap-3 border-t border-outline-variant/50 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-body-lg font-bold text-on-surface">{{ __('ترحيل قاعدة البيانات') }}</h3>
                            <p class="mt-1 font-body-sm text-on-surface-variant">{{ __('يُنشئ الجداول الناقصة حسب باقة العميل. آمن للتكرار — لا يمسّ البيانات الموجودة.') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.licenses.migrate', $license->id) }}"
                            x-data="{ submitting: false }"
                            @submit.prevent="if (confirm('{{ __('تشغيل ترحيلات قاعدة البيانات لهذا الترخيص؟') }}')) { submitting = true; $el.submit() }">
                            @csrf
                            <button type="submit" :disabled="submitting" class="flex items-center gap-2 rounded-xl bg-primary px-5 py-3 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-60" title="{{ __('تشغيل الترحيلات') }}">
                                <span class="material-symbols-outlined !text-xl" :class="submitting && 'animate-spin'"
                                    x-text="submitting ? 'progress_activity' : 'database'">database</span>
                                <span x-text="submitting ? @js(__('جارٍ التشغيل...')) : @js(__('تشغيل الترحيلات'))">{{ __('تشغيل الترحيلات') }}</span>
                            </button>
                        </form>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.licenses.change-package', $license->id) }}" class="mt-6 flex flex-col gap-3 border-t border-outline-variant/50 pt-6 sm:flex-row sm:items-end"
                    x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <div class="flex-1 space-y-1.5">
                        <label for="admin-licenses-show-package-id" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تغيير الباقة') }}</label>
                        <select id="admin-licenses-show-package-id" name="packageId" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                            @foreach ($packages as $package)
                                <option value="{{ $package->id }}" @selected($license->package_id === $package->id)>{{ $package->name }} - {{ number_format($package->price, 2) }} ₪</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" :disabled="submitting" class="rounded-xl bg-surface-container px-5 py-3 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high disabled:cursor-not-allowed disabled:opacity-60"
                        x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ الباقة'))"
                        title="{{ __('حفظ الباقة') }}">{{ __('حفظ الباقة') }}</button>
                </form>

                <div class="mt-6 border-t border-outline-variant/50 pt-6"
                    x-data="{ submitting: false, vertical: @js($license->businessType()->value) }">
                    <h3 class="font-body-lg font-bold text-on-surface">{{ __('تصنيف النشاط') }}</h3>
                    <p class="mt-1 font-body-sm text-on-surface-variant">{{ __('يحدّد الوحدات المتاحة داخل النسخة. التغيير لا يحذف أي بيانات — الوحدات التي تُطفأ تحتفظ بسجلّاتها وتختفي من الواجهة فقط.') }}</p>

                    <form method="POST" action="{{ route('admin.licenses.change-business-type', $license->id) }}" class="mt-4"
                        @submit.prevent="if (confirm('{{ __('تغيير تصنيف النشاط لهذه النسخة؟') }}')) { submitting = true; $el.submit() }">
                        @csrf
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($businessTypes as $type)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 transition"
                                    :class="vertical === @js($type->value)
                                        ? 'border-primary bg-primary text-on-primary'
                                        : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'">
                                    <input type="radio" name="businessType" value="{{ $type->value }}" x-model="vertical" class="sr-only" />
                                    <span class="material-symbols-outlined !text-xl flex-shrink-0">{{ $type->icon() }}</span>
                                    <span class="font-body-sm leading-tight">{{ $type->label() }}</span>
                                </label>
                            @endforeach
                        </div>

                        @foreach ($businessTypes as $type)
                            <div x-show="vertical === @js($type->value)" x-cloak class="mt-3 rounded-xl bg-surface-container-low p-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($type->capabilities() as $capability)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-secondary-container px-2 py-1 font-label-sm text-label-sm text-on-secondary-container">
                                            <span class="material-symbols-outlined !text-sm">{{ $capability->icon() }}</span>{{ $capability->label() }}
                                        </span>
                                    @endforeach
                                </div>
                                <p class="mt-2 font-body-sm text-outline" dir="ltr">{{ implode(' · ', $type->standards()) }}</p>
                            </div>
                        @endforeach

                        <button type="submit" :disabled="submitting || vertical === @js($license->businessType()->value)"
                            class="mt-4 rounded-xl bg-surface-container px-5 py-3 font-body-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ التصنيف'))"
                            title="{{ __('حفظ التصنيف') }}">{{ __('حفظ التصنيف') }}</button>
                    </form>
                </div>
            </x-card>

            <x-card title="{{ __('آخر نشاط المستأجر') }}">
                @if ($recentActivity->isEmpty())
                    <p class="py-6 text-center font-body-sm text-on-surface-variant">{{ __('لا يوجد نشاط') }}</p>
                @else
                    <ul class="divide-y divide-outline-variant/50">
                        @foreach ($recentActivity as $log)
                            <li class="py-3">
                                <div class="flex items-center gap-2">
                                    <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $actionPill($log->action?->value) }}">
                                        {{ $actionLabels[$log->action?->value] ?? ($log->action?->value ?? '-') }}
                                    </span>
                                    <p class="truncate font-body-sm font-bold">{{ $log->entity_label ?? '-' }}</p>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant">{{ $log->actor_name ?? '-' }} · {{ $log->created_at?->diffForHumans() }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

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

                @if ($teamTotal > $team->count())
                    <p class="mt-4 flex items-start gap-1.5 border-t border-outline-variant/50 pt-3 text-[11px] text-on-surface-variant">
                        <span class="material-symbols-outlined !text-base flex-shrink-0">info</span>
                        {{ __('يُعرض :shown من أصل :total عضواً.', ['shown' => $team->count(), 'total' => $teamTotal]) }}
                    </p>
                @endif
            @endif
        </x-card>

        <x-confirm-modal />
    </div>
@endsection
