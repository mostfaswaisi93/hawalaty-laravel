{{--
    Account picker, shared by the order form and the expense form.

    Two states, never both:
      - nothing chosen yet -> a scrollable list of account cards to pick from;
      - chosen             -> ONE card summarising it, with a "تغيير" button
                              that clears the choice and brings the list back.

    Params:
      $model      Alpine property holding the chosen id (e.g. 'accountId')
      $inputName  the POST field name (e.g. 'accountId')
      $listExpr   JS expression returning the accounts to offer
      $id         unique prefix for element ids
      $empty      message shown when $listExpr is empty
--}}
<div class="space-y-2">
    <input type="hidden" name="{{ $inputName }}" :value="{{ $model }}" />

    {{-- ── chosen: the card ── --}}
    <template x-if="{{ $model }}">
        <div class="reveal-scale flex items-center gap-3 rounded-2xl border-2 border-primary bg-primary/5 p-4">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-primary text-on-primary">
                <span class="material-symbols-outlined" x-text="{ bank: 'account_balance', wallet: 'account_balance_wallet', cash: 'payments' }[accountById({{ $model }})?.kind] || 'account_balance'"></span>
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-body-lg font-bold text-on-surface" x-text="accountById({{ $model }})?.holder"></p>
                <p class="truncate font-body-sm text-body-sm text-on-surface-variant">
                    <span x-text="accountById({{ $model }})?.institution === accountById({{ $model }})?.holder ? '' : accountById({{ $model }})?.institution"></span>
                    <template x-if="accountById({{ $model }})?.type">
                        <span> · <span x-text="accountById({{ $model }})?.type"></span></span>
                    </template>
                    <template x-if="accountById({{ $model }})?.currency">
                        <span> · <span dir="ltr" x-text="accountById({{ $model }})?.currency"></span></span>
                    </template>
                </p>
            </div>
            <button type="button" @click="{{ $model }} = ''"
                class="flex flex-shrink-0 items-center gap-1 rounded-xl border border-primary/40 bg-surface-container-lowest px-3 py-2 font-body-sm font-bold text-primary transition-colors hover:bg-primary/10"
                title="{{ __('تغيير') }}">
                <span class="material-symbols-outlined !text-lg">swap_horiz</span>
                {{ __('تغيير') }}
            </button>
        </div>
    </template>

    {{-- ── not chosen: the list ── --}}
    <template x-if="!{{ $model }}">
        <div class="space-y-2">
            <template x-if="({{ $listExpr }}).length === 0">
                <p class="flex items-start gap-1.5 rounded-xl border border-dashed border-outline-variant bg-surface-container-low px-4 py-3 font-body-sm text-body-sm text-on-surface-variant">
                    <span class="material-symbols-outlined !text-base">info</span>
                    {{ $empty }}
                </p>
            </template>

            <div class="max-h-64 space-y-2 overflow-y-auto" role="listbox" aria-label="{{ __('اختر الحساب') }}">
                <template x-for="acc in {{ $listExpr }}" :key="'{{ $id }}-' + acc.id">
                    <button type="button" role="option" @click="{{ $model }} = acc.id"
                        class="flex w-full items-center gap-3 rounded-2xl border border-outline-variant bg-surface-container-lowest p-3 text-start transition-all hover:border-primary hover:bg-primary/5 active:scale-[0.99]">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <span class="material-symbols-outlined !text-xl" x-text="{ bank: 'account_balance', wallet: 'account_balance_wallet', cash: 'payments' }[acc.kind] || 'account_balance'"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-body-sm font-bold text-on-surface" x-text="acc.holder"></span>
                            <span class="block truncate text-[11px] text-on-surface-variant">
                                <span x-text="acc.institution === acc.holder ? '' : acc.institution"></span>
                                <template x-if="acc.type"><span> · <span x-text="acc.type"></span></span></template>
                            </span>
                        </span>
                        <span class="flex-shrink-0 font-numeral-lg text-body-sm font-bold text-on-surface-variant" dir="ltr" x-text="acc.currency"></span>
                    </button>
                </template>
            </div>
        </div>
    </template>
</div>
