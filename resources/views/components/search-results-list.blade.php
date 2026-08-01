@php

    $row = 'flex items-center gap-3 rounded-xl px-2 py-2 transition-colors scroll-mt-1';
    $rowActive = 'bg-primary/10 ring-1 ring-primary/30';
    $rowIdle = 'hover:bg-surface-container-low';
@endphp

<template x-if="showRecent">
    <div>
        <div class="flex items-center justify-between px-2 py-1">
            <p class="font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('عمليات بحث سابقة') }}</p>
            <button type="button" @click="clearRecent()" class="font-body-sm text-[11px] font-bold text-primary hover:underline">{{ __('مسح') }}</button>
        </div>
        <template x-for="term in recent" :key="term">
            <button type="button" @click="runRecent(term)"
                class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-start transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined flex-shrink-0 !text-lg text-outline">history</span>
                <span class="min-w-0 flex-1 truncate font-body-sm text-on-surface" x-text="term"></span>
                <span class="material-symbols-outlined flex-shrink-0 !text-lg text-outline rtl:-scale-x-100">north_east</span>
            </button>
        </template>
    </div>
</template>

<template x-if="searched && !hasResults">
    <div class="flex flex-col items-center gap-1 p-6 text-center">
        <span class="material-symbols-outlined !text-3xl text-outline/50">search_off</span>
        <p class="font-body-sm font-bold text-on-surface">{{ __('لا نتائج مطابقة') }}</p>
        <p class="font-body-sm text-[11px] text-outline">{{ __('جرّب اسم العميل أو رقم الجوال') }}</p>
    </div>
</template>

<template x-if="results.orders.length">
    <div class="mb-1">
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('الطلبات') }}</p>
        <template x-for="(o, i) in results.orders" :key="o.id">
            <a :href="o.url" role="option" :data-result-index="indexOf('orders', i)"
                :aria-selected="cursor === indexOf('orders', i)"
                @mouseenter="cursor = indexOf('orders', i)" @click="remember(query.trim())"
                class="{{ $row }}" :class="cursor === indexOf('orders', i) ? '{{ $rowActive }}' : '{{ $rowIdle }}'">
                <span class="material-symbols-outlined flex-shrink-0 text-primary">receipt_long</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-body-sm font-bold text-on-surface">
                        <template x-for="(p, pi) in parts(o.customer)" :key="pi"><span :class="p.hit && 'text-primary underline decoration-primary/40'" x-text="p.text"></span></template>
                    </span>
                    <span class="block truncate text-[11px] text-on-surface-variant">
                        <span x-show="o.phone" dir="ltr" class="font-numeral-lg" x-text="o.phone"></span>
                        <span x-show="o.phone && o.itemName" class="text-outline"> · </span>
                        <span x-text="o.itemName"></span>
                    </span>
                </span>
                <span class="flex-shrink-0 font-numeral-lg text-xs font-bold text-on-surface" dir="ltr" x-text="o.amount.toFixed(2) + ' ₪'"></span>
            </a>
        </template>
    </div>
</template>

<template x-if="results.customers.length">
    <div class="mb-1">
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('العملاء') }}</p>
        <template x-for="(c, i) in results.customers" :key="c.id">
            <a :href="c.url" role="option" :data-result-index="indexOf('customers', i)"
                :aria-selected="cursor === indexOf('customers', i)"
                @mouseenter="cursor = indexOf('customers', i)" @click="remember(query.trim())"
                class="{{ $row }}" :class="cursor === indexOf('customers', i) ? '{{ $rowActive }}' : '{{ $rowIdle }}'">
                <span class="material-symbols-outlined flex-shrink-0 text-secondary">person</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-body-sm font-bold text-on-surface">
                        <template x-for="(p, pi) in parts(c.name)" :key="pi"><span :class="p.hit && 'text-primary underline decoration-primary/40'" x-text="p.text"></span></template>
                    </span>
                    <span class="block truncate font-numeral-lg text-[11px] text-on-surface-variant" dir="ltr">
                        <template x-for="(p, pi) in parts(c.phone)" :key="pi"><span :class="p.hit && 'font-bold text-primary'" x-text="p.text"></span></template>
                    </span>
                </span>
                <span x-show="c.orderCount" class="flex-shrink-0 rounded-full bg-surface-container px-2 py-0.5 font-numeral-lg text-[11px] font-bold text-on-surface-variant"
                    x-text="c.orderCount"></span>
            </a>
        </template>
    </div>
</template>

<template x-if="results.banks.length">
    <div class="mb-1">
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('البنوك') }}</p>
        <template x-for="(b, i) in results.banks" :key="b.id">
            <a :href="b.url" role="option" :data-result-index="indexOf('banks', i)"
                :aria-selected="cursor === indexOf('banks', i)"
                @mouseenter="cursor = indexOf('banks', i)" @click="remember(query.trim())"
                class="{{ $row }}" :class="cursor === indexOf('banks', i) ? '{{ $rowActive }}' : '{{ $rowIdle }}'">
                <span class="material-symbols-outlined flex-shrink-0 text-tertiary">account_balance</span>
                <span class="min-w-0 flex-1 truncate font-body-sm font-bold text-on-surface">
                    <template x-for="(p, pi) in parts(b.name)" :key="pi"><span :class="p.hit && 'text-primary underline decoration-primary/40'" x-text="p.text"></span></template>
                </span>
                <span x-show="!b.active" class="flex-shrink-0 rounded-full bg-surface-container px-2 py-0.5 text-[11px] font-bold text-outline">{{ __('غير نشط') }}</span>
            </a>
        </template>
    </div>
</template>

<template x-if="results.menuItems.length">
    <div>
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline"
            x-text="itemLabel || '{{ __('الأصناف') }}'"></p>
        <template x-for="(m, i) in results.menuItems" :key="m.id">
            <a :href="m.url" role="option" :data-result-index="indexOf('menuItems', i)"
                :aria-selected="cursor === indexOf('menuItems', i)"
                @mouseenter="cursor = indexOf('menuItems', i)" @click="remember(query.trim())"
                class="{{ $row }}" :class="cursor === indexOf('menuItems', i) ? '{{ $rowActive }}' : '{{ $rowIdle }}'">
                <span class="material-symbols-outlined flex-shrink-0 text-primary" x-text="m.icon"></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-body-sm font-bold text-on-surface">
                        <template x-for="(p, pi) in parts(m.name)" :key="pi"><span :class="p.hit && 'text-primary underline decoration-primary/40'" x-text="p.text"></span></template>
                    </span>
                    <span x-show="m.categoryName" class="block truncate text-[11px] text-on-surface-variant" x-text="m.categoryName"></span>
                </span>
                <span class="flex-shrink-0 font-numeral-lg text-xs font-bold text-on-surface" dir="ltr" x-text="m.price.toFixed(2) + ' ₪'"></span>
            </a>
        </template>
    </div>
</template>

<template x-if="results.teamMembers.length">
    <div>
        <p class="px-2 py-1 font-body-sm text-[11px] font-bold uppercase tracking-wide text-outline">{{ __('الفريق') }}</p>
        <template x-for="(t, i) in results.teamMembers" :key="t.id">
            <a :href="t.url" role="option" :data-result-index="indexOf('teamMembers', i)"
                :aria-selected="cursor === indexOf('teamMembers', i)"
                @mouseenter="cursor = indexOf('teamMembers', i)" @click="remember(query.trim())"
                class="{{ $row }}" :class="cursor === indexOf('teamMembers', i) ? '{{ $rowActive }}' : '{{ $rowIdle }}'">
                <span class="material-symbols-outlined flex-shrink-0 text-primary">person</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-body-sm font-bold text-on-surface">
                        <template x-for="(p, pi) in parts(t.name)" :key="pi"><span :class="p.hit && 'text-primary underline decoration-primary/40'" x-text="p.text"></span></template>
                    </span>
                    <span class="block truncate text-[11px] text-on-surface-variant" dir="ltr" x-text="t.email"></span>
                </span>
                <span x-show="t.roleName" class="flex-shrink-0 rounded-full bg-surface-container-high px-2 py-0.5 text-[11px] text-on-surface-variant" x-text="t.roleName"></span>
            </a>
        </template>
    </div>
</template>
