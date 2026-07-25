@extends('layouts.admin')

@section('title', __('الشركاء — حوالاتي'))

@section('content')
    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: 'add',
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null,
            form: { id: '', name: '{{ old('name', '') }}', percentage: '{{ old('percentage', '') }}' },
            openAdd() { this.mode = 'add'; this.submitting = false; this.form = { id: '', name: '', percentage: '' }; this.open = true; },
            openEdit(partner) { this.mode = 'edit'; this.submitting = false; this.form = { ...partner }; this.open = true; },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الشركاء') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('شركاء المنصّة ونسبة حصّة كلٍّ منهم.') }}</p>
            </div>
            <button type="button" @click="openAdd()"
                class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95">
                <span class="material-symbols-outlined !text-xl">add</span>{{ __('إضافة شريك') }}
            </button>
        </header>

        <section class="tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6">
            @if ($partners->isEmpty())
                <div class="flex flex-col items-center gap-3 py-16 text-center">
                    <span class="material-symbols-outlined !text-5xl text-outline/50">handshake</span>
                    <p class="font-body-lg text-on-surface-variant">{{ __('لا يوجد شركاء بعد') }}</p>
                </div>
            @else
                <x-table-responsive :headers="[__('الاسم'), __('النسبة'), __('إجراءات')]">
                    <x-slot:table>
                        @foreach ($partners as $partner)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-secondary/10 text-secondary"><span class="material-symbols-outlined !text-xl">handshake</span></div>
                                        <span class="font-body-sm font-bold">{{ $partner->name }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-bold" dir="ltr">{{ number_format($partner->percentage, 2) }}%</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="openEdit(@js(['id' => $partner->id, 'name' => $partner->name, 'percentage' => (float) $partner->percentage]))" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}"><span class="material-symbols-outlined !text-xl">edit</span></button>
                                        <form method="POST" action="{{ route('admin.partners.destroy', $partner->id) }}" id="del-partner-{{ $partner->id }}">@csrf @method('DELETE')</form>
                                        <button type="button" aria-label="{{ __('حذف') }}" @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذا الشريك؟') }}'; confirmFormId = 'del-partner-{{ $partner->id }}'" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined !text-xl">delete</span></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>
                    <x-slot:cards>
                        @foreach ($partners as $partner)
                            <div class="tonal-elevation rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between">
                                    <span class="font-body-sm font-bold">{{ $partner->name }}</span>
                                    <span class="font-bold text-secondary" dir="ltr">{{ number_format($partner->percentage, 2) }}%</span>
                                </div>
                                <div class="mt-3 flex items-center justify-end gap-1 border-t border-outline-variant/50 pt-3">
                                    <button type="button" @click="openEdit(@js(['id' => $partner->id, 'name' => $partner->name, 'percentage' => (float) $partner->percentage]))" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-high hover:text-primary" aria-label="{{ __('تعديل') }}"><span class="material-symbols-outlined !text-lg">edit</span></button>
                                    <form method="POST" action="{{ route('admin.partners.destroy', $partner->id) }}" id="del-partner-card-{{ $partner->id }}">@csrf @method('DELETE')</form>
                                    <button type="button" aria-label="{{ __('حذف') }}" @click="confirmOpen = true; confirmMessage = '{{ __('حذف هذا الشريك؟') }}'; confirmFormId = 'del-partner-card-{{ $partner->id }}'" class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined !text-lg">delete</span></button>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>

                <x-table-footer :paginator="$partners" :perPage="$perPage" />
            @endif
        </section>

        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            <div x-show="open" @keydown.escape.window="open = false" role="dialog" aria-modal="true"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-3xl bg-surface-container-lowest p-6 shadow-2xl">
                <h3 class="mb-5 font-headline-md text-headline-md" x-text="mode === 'edit' ? '{{ __('تعديل الشريك') }}' : '{{ __('إضافة شريك') }}'"></h3>
                <form :action="mode === 'edit' ? '{{ url('admin/partners') }}/' + form.id : '{{ route('admin.partners.store') }}'" method="POST" class="space-y-4" @submit="submitting = true">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم الشريك') }}</label>
                        <input name="name" x-model="form.name" required class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('النسبة (%)') }}</label>
                        <input type="number" step="0.01" min="0" max="100" name="percentage" x-model="form.percentage" required dir="ltr" placeholder="0" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="open = false" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting" class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:opacity-50" x-text="submitting ? '{{ __('جارٍ الحفظ...') }}' : (mode === 'edit' ? '{{ __('حفظ') }}' : '{{ __('إضافة') }}')"></button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
