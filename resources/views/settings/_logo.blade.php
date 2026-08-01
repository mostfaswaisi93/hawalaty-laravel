<section class="reveal-up tonal-elevation rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-5 md:p-6 lg:col-span-2" style="--d:120ms"
    x-data="{
        dragging: false,
        uploading: false,
        preview: @js($logo),
        pick(files) {
            if (!files || !files.length) return;
            const file = files[0];
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = e => { this.preview = e.target.result; };
            reader.readAsDataURL(file);
            this.uploading = true;
            this.$refs.form.submit();
        },
        drop(e) {
            this.dragging = false;
            this.$refs.input.files = e.dataTransfer.files;
            this.pick(e.dataTransfer.files);
        },
    }">

    <div class="mb-5 flex items-center gap-3">
        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <span class="material-symbols-outlined">image</span>
        </span>
        <div>
            <h2 class="font-headline-md text-headline-md text-on-surface">{{ __('شعار المشروع') }}</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ __('يظهر أعلى الفاتورة بدل اسم المشروع.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.logo.update') }}" enctype="multipart/form-data" x-ref="form">
        @csrf
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" x-ref="input" @change="pick($event.target.files)"
            class="sr-only" id="settings-logo" />

        <template x-if="preview">
            <div class="space-y-3">
                <div class="flex items-center justify-center rounded-2xl border border-outline-variant bg-white p-5">
                    <img :src="preview" alt="{{ __('شعار المشروع') }}" class="max-h-28 max-w-full object-contain" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="$refs.input.click()" :disabled="uploading"
                        class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-body-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container disabled:opacity-50">
                        <span class="material-symbols-outlined !text-lg">swap_horiz</span>
                        <span x-text="uploading ? '{{ __('جارٍ الرفع...') }}' : '{{ __('تغيير الشعار') }}'"></span>
                    </button>
                    <button type="button" @click="$refs.deleteForm.submit()"
                        class="flex items-center gap-2 rounded-xl border border-error/40 px-4 py-2.5 font-body-sm font-bold text-error transition-colors hover:bg-error/10">
                        <span class="material-symbols-outlined !text-lg">delete</span>
                        {{ __('إزالة') }}
                    </button>
                </div>
            </div>
        </template>

        <template x-if="!preview">
            <label for="settings-logo"
                @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed p-8 text-center transition-colors"
                :class="dragging ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary hover:bg-surface-container-low'">
                <span class="material-symbols-outlined !text-4xl text-outline" :class="dragging && '!text-primary'">cloud_upload</span>
                <span class="font-body-lg font-bold text-on-surface" x-text="uploading ? '{{ __('جارٍ الرفع...') }}' : '{{ __('اسحب الشعار هنا') }}'"></span>
                <span class="font-body-sm text-body-sm text-on-surface-variant">{{ __('أو اضغط للاختيار · PNG أو JPG أو WEBP · حتى ١ ميغابايت') }}</span>
            </label>
        </template>

        @error('logo')<p class="mt-2 font-body-sm text-body-sm text-error">{{ $message }}</p>@enderror
    </form>

    <form method="POST" action="{{ route('settings.logo.destroy') }}" x-ref="deleteForm" class="hidden">
        @csrf @method('DELETE')
    </form>
</section>
