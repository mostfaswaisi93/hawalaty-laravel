@if (session('success'))
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/10 p-4 text-primary">
        <span class="material-symbols-outlined text-primary">check_circle</span>
        <p class="font-body-sm text-body-sm">{{ session('success') }}</p>
    </div>
@endif

@if (session('error'))
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-error/20 bg-error-container/40 p-4 text-on-error-container">
        <span class="material-symbols-outlined text-error">error</span>
        <p class="font-body-sm text-body-sm">{{ session('error') }}</p>
    </div>
@endif

@if ($errors->any() && ! $errors->has('login'))
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-error/20 bg-error-container/40 p-4 text-on-error-container">
        <span class="material-symbols-outlined text-error">error</span>
        <div class="font-body-sm text-body-sm">
            <p class="font-bold">يرجى تصحيح الأخطاء التالية:</p>
            <ul class="mt-1 list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@error('login')
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-error/20 bg-error-container/40 p-4 text-on-error-container">
        <span class="material-symbols-outlined text-error">error</span>
        <p class="font-body-sm text-body-sm">{{ $message }}</p>
    </div>
@enderror
