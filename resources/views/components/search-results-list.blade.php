{{--
    Shared result-group markup for the global search (header pill on
    desktop, full-screen overlay on mobile) — both read the same
    `globalSearch()` Alpine state (results/searched/hasResults), so this
    partial avoids duplicating the four x-for groups in two places.
--}}
<template x-if="searched && !hasResults">
    <p class="p-4 text-center font-body-sm text-outline">{{ __('لا نتائج مطابقة') }}</p>
</template>

<template x-if="results.orders.length">
    <div class="mb-1">
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('الطلبات') }}</p>
        <template x-for="o in results.orders" :key="o.id">
            <a :href="o.url" class="flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined flex-shrink-0 text-primary">receipt_long</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-body-sm font-bold text-on-surface" x-text="o.customer"></span>
                    <span class="block truncate text-[11px] text-on-surface-variant" x-text="o.itemName"></span>
                </span>
                <span class="flex-shrink-0 font-numeral-lg text-xs font-bold text-on-surface" x-text="o.amount.toFixed(2) + ' ₪'"></span>
            </a>
        </template>
    </div>
</template>

<template x-if="results.customers.length">
    <div class="mb-1">
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('العملاء') }}</p>
        <template x-for="c in results.customers" :key="c.id">
            <a :href="c.url" class="flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined flex-shrink-0 text-secondary">person</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-body-sm font-bold text-on-surface" x-text="c.name"></span>
                    <span class="block truncate text-[11px] text-on-surface-variant" x-text="c.phone || ''"></span>
                </span>
            </a>
        </template>
    </div>
</template>

<template x-if="results.banks.length">
    <div class="mb-1">
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('البنوك') }}</p>
        <template x-for="b in results.banks" :key="b.id">
            <a :href="b.url" class="flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined flex-shrink-0 text-tertiary">account_balance</span>
                <span class="truncate font-body-sm font-bold text-on-surface" x-text="b.name"></span>
            </a>
        </template>
    </div>
</template>

<template x-if="results.menuItems.length">
    <div>
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('الأصناف') }}</p>
        <template x-for="m in results.menuItems" :key="m.id">
            <a :href="m.url" class="flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined flex-shrink-0 text-primary">restaurant_menu</span>
                <span class="min-w-0 flex-1 truncate font-body-sm font-bold text-on-surface" x-text="m.name"></span>
                <span class="flex-shrink-0 font-numeral-lg text-xs font-bold text-on-surface" x-text="m.price.toFixed(2) + ' ₪'"></span>
            </a>
        </template>
    </div>
</template>
