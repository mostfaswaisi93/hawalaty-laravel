@extends('layouts.app')

@section('title', __('الأدوار والصلاحيات - حوالاتي'))

@section('content')
    @php
        $roleLabel = fn ($role) => match ($role->key?->value) {
            'owner' => __('مالك'),
            'manager' => __('مدير'),
            'staff' => __('موظف'),
            default => $role->name,
        };
    @endphp

    <div class="page-enter space-y-6"
        x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            mode: 'add',
            submitting: false,
            confirmOpen: false, confirmMessage: '', confirmFormId: null, confirmTitle: '', confirmLabel: '',
            form: { id: '', name: '{{ old('name', '') }}', permissions: @js(old('permissions', [])) },
            openAdd() { this.mode = 'add'; this.submitting = false; this.form = { id: '', name: '', permissions: [] }; this.open = true; },
            openEdit(role) { this.mode = 'edit'; this.submitting = false; this.form = { ...role }; this.open = true; },
            toggle(key) {
                const i = this.form.permissions.indexOf(key);
                i === -1 ? this.form.permissions.push(key) : this.form.permissions.splice(i, 1);
            },
            has(key) { return this.form.permissions.includes(key); },
        }">

        <header class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('الأدوار والصلاحيات') }}</h1>
                <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('حدّد ما يستطيع كل دور فعله، وأنشئ أدواراً تناسب فريقك.') }}</p>
            </div>
            <button type="button" @click="openAdd()"
                class="press flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container"
                title="{{ __('دور جديد') }}">
                <span class="material-symbols-outlined !text-xl">add</span>{{ __('دور جديد') }}
            </button>
        </header>

        <div class="stagger grid gap-5 lg:grid-cols-2">
            @foreach ($roles as $role)
                @php $granted = $role->permissions->pluck('key')->map(fn ($k) => $k instanceof \App\Enums\PermissionKey ? $k->value : $k); @endphp

                <section class="tonal-elevation flex flex-col rounded-3xl border border-outline-variant/50 bg-surface-container-lowest">
                    <div class="flex items-start justify-between gap-3 border-b border-outline-variant/50 p-5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl {{ $role->is_system ? 'bg-secondary-container/20 text-secondary' : 'bg-primary/10 text-primary' }}">
                                <span class="material-symbols-outlined">{{ $role->is_system ? 'verified_user' : 'admin_panel_settings' }}</span>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate font-headline-md text-headline-md text-on-surface">{{ $roleLabel($role) }}</h2>
                                    @if ($role->is_system)
                                        {{-- Built-in roles are cloned at provisioning and deliberately
                                             immutable, so the badge explains why the actions are absent. --}}
                                        <span class="rounded-full bg-surface-container-high px-2.5 py-0.5 text-[10px] font-bold text-on-surface-variant">{{ __('دور أساسي') }}</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 font-body-sm text-body-sm text-outline">
                                    {{ trans_choice('{0} لا أعضاء|{1} عضو واحد|[2,*] :count أعضاء', $role->team_members_count, ['count' => $role->team_members_count]) }}
                                </p>
                            </div>
                        </div>

                        @unless ($role->is_system)
                            <div class="flex flex-shrink-0 items-center gap-1">
                                <button type="button"
                                    @click="openEdit(@js(['id' => $role->id, 'name' => $role->name, 'permissions' => $granted->values()]))"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-primary"
                                    aria-label="{{ __('تعديل') }}" title="{{ __('تعديل') }}">
                                    <span class="material-symbols-outlined !text-xl">edit</span>
                                </button>

                                <form method="POST" action="{{ route('roles.destroy', $role->id) }}" id="delete-role-{{ $role->id }}" class="hidden">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button"
                                    @disabled($role->team_members_count > 0)
                                    @click="confirmOpen = true;
                                            confirmTitle = @js(__('حذف الدور'));
                                            confirmMessage = @js(__('سيتم حذف هذا الدور نهائياً.'));
                                            confirmLabel = @js(__('حذف'));
                                            confirmFormId = 'delete-role-{{ $role->id }}'"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="{{ __('حذف') }}"
                                    title="{{ $role->team_members_count > 0 ? __('لا يمكن حذف دور له أعضاء') : __('حذف') }}">
                                    <span class="material-symbols-outlined !text-xl">delete</span>
                                </button>
                            </div>
                        @endunless
                    </div>

                    <div class="flex-1 p-5">
                        @if ($granted->isEmpty())
                            <p class="py-4 text-center font-body-sm text-body-sm text-outline">{{ __('لا صلاحيات ممنوحة') }}</p>
                        @else
                            <ul class="space-y-2">
                                @foreach ($permissions as $permission)
                                    @continue (! $granted->contains($permission->value))
                                    <li class="flex items-center gap-2.5 font-body-sm text-body-sm text-on-surface">
                                        <span class="material-symbols-outlined !text-lg text-success">check_circle</span>
                                        {{ $permission->label() }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        {{-- add / edit --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none">
            <div class="fixed inset-0 bg-black/40" x-show="open" x-transition.opacity></div>
            {{-- Focus the first field on open, like every other modal in the app:
                 without it a keyboard user is still on the page behind the dialog. --}}
            <div x-show="open" @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-labelledby="role-modal-title"
                x-init="$watch('open', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus()))"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative z-10 max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto overflow-x-hidden rounded-3xl bg-surface-container-lowest shadow-2xl">
                <x-modal-close on-close="open = false" />
                <h3 id="role-modal-title" class="border-b border-outline-variant/60 py-4 ps-6 pe-14 font-headline-md text-headline-md"
                    x-text="mode === 'edit' ? @js(__('تعديل الدور')) : @js(__('دور جديد'))"></h3>

                <form :action="mode === 'edit' ? '{{ url('roles') }}/' + form.id : '{{ route('roles.store') }}'" method="POST" class="space-y-5 p-6" @submit="submitting = true">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="space-y-1.5">
                        <label for="role-name" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('اسم الدور') }}</label>
                        <input id="role-name" name="name" x-model="form.name" required maxlength="255" placeholder="{{ __('مثال: أمين صندوق') }}"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary" />
                        @error('name')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-2">
                        <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('الصلاحيات') }}</p>

                        {{-- Each permission is submitted as its own checkbox rather than a
                             multi-select: the description matters as much as the name when
                             deciding whether to grant something. --}}
                        @foreach ($permissions as $permission)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors"
                                :class="has(@js($permission->value)) ? 'border-primary bg-primary/5' : 'border-outline-variant hover:bg-surface-container-low'">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->value }}"
                                    :checked="has(@js($permission->value))" @click="toggle(@js($permission->value))"
                                    class="mt-0.5 h-5 w-5 flex-shrink-0 rounded accent-primary" />
                                <span class="flex min-w-0 items-start gap-2.5">
                                    <span class="material-symbols-outlined !text-xl text-on-surface-variant">{{ $permission->icon() }}</span>
                                    <span class="min-w-0">
                                        <span class="block font-body-sm font-bold text-on-surface">{{ $permission->label() }}</span>
                                        <span class="mt-0.5 block font-body-sm text-body-sm text-on-surface-variant">{{ $permission->description() }}</span>
                                    </span>
                                </span>
                            </label>
                        @endforeach

                        @error('permissions')<p class="font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="-mx-6 -mb-6 mt-6 flex justify-end gap-3 border-t border-outline-variant/60 bg-surface-container-low px-6 py-4">
                        <button type="button" @click="open = false"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container"
                            title="{{ __('إلغاء') }}">{{ __('إلغاء') }}</button>
                        <button type="submit" :disabled="submitting || !form.permissions.length"
                            class="rounded-xl bg-primary px-6 py-2.5 font-body-sm font-bold text-on-primary shadow-lg transition-all hover:bg-primary-container hover:text-on-primary-container active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            title="{{ __('حفظ') }}"
                            x-text="submitting ? @js(__('جارٍ الحفظ...')) : @js(__('حفظ'))">{{ __('حفظ') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <x-confirm-modal />
    </div>
@endsection
