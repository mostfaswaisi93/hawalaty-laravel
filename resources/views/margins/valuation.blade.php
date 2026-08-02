@extends('layouts.app')

@section('title', __('التقييم والراكد - حوالاتي'))

@section('content')
    <div class="page-enter space-y-6">
        <header>
            <h1 class="font-display-lg text-display-lg text-on-surface">{{ __('التقييم والراكد') }}</h1>
            <p class="mt-1 font-body-lg text-on-surface-variant">{{ __('كم من المال واقف على الرف، وكم منه لن يعود.') }}</p>
        </header>

        @include('margins._tabs', ['active' => 'valuation'])

        <div class="reveal-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" style="--d:80ms">
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('قيمة المخزون') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($valuation['total'], 2) }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __('بالمتوسط المرجّح') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تخفيض واجب — IAS 2') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $valuation['writeDown'] > 0 ? 'text-error' : '' }}" dir="ltr">
                    {{ number_format($valuation['writeDown'], 2) }}
                </p>
                <p class="mt-1 font-body-sm text-outline">{{ __('تكلفة تجاوزت صافي القيمة القابلة للتحقّق') }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('رأس مال راكد') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold" dir="ltr">{{ number_format($slowMovers->sum('tied_up'), 2) }}</p>
                <p class="mt-1 font-body-sm text-outline">{{ __(':count صنف بلا حركة', ['count' => $slowMovers->count()]) }}</p>
            </x-card>
            <x-card>
                <p class="font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('تكلفة التالف في الفترة') }}</p>
                <p class="mt-1 font-numeral-lg text-headline-md font-bold {{ $waste > 0 ? 'text-error' : '' }}" dir="ltr">{{ number_format($waste, 2) }}</p>
            </x-card>
        </div>

        @if ($valuation['writeDown'] > 0)
            <div class="reveal-up flex items-start gap-3 rounded-2xl border border-error/30 bg-error-container/10 p-4" style="--d:120ms">
                <span class="material-symbols-outlined flex-shrink-0 text-error">trending_down</span>
                <div class="min-w-0">
                    <p class="font-body-sm font-bold text-error">{{ __('مخزون محمول فوق ما سيعود منه') }}</p>
                    <p class="mt-1 font-body-sm text-error">
                        {{ __('يقيس المعيار المخزون بأقل التكلفة وصافي القيمة القابلة للتحقّق. صنف صارت تكلفته أعلى من سعر بيعه خسارتُه وقعت فعلًا سواء سُجّلت أم لا.') }}
                    </p>
                </div>
            </div>
        @endif

        <x-card title="{{ __('المخزون الراكد') }}">
            <form method="GET" action="{{ route('margins.valuation') }}" class="mb-5 flex flex-wrap items-end gap-3">
                <div class="space-y-1.5">
                    <label for="days" class="block font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant">{{ __('بلا حركة منذ') }}</label>
                    <select id="days" name="days" onchange="this.form.submit()"
                        class="rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-lg outline-none focus:border-transparent focus:ring-2 focus:ring-primary">
                        @foreach ([30, 60, 90, 180, 365] as $option)
                            <option value="{{ $option }}" @selected($days === $option)>{{ __(':days يومًا', ['days' => $option]) }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if ($slowMovers->isEmpty())
                <p class="py-8 text-center font-body-lg text-on-surface-variant">{{ __('كل صنف في المخزون تحرّك خلال هذه المدة') }}</p>
            @else
                <x-table-responsive :headers="[
                    $naming->item(),
                    ['label' => __('الرصيد'), 'align' => 'end'],
                    ['label' => __('رأس المال المجمَّد'), 'align' => 'end'],
                    __('آخر بيعة'),
                ]">
                    <x-slot:table>
                        @foreach ($slowMovers as $row)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $row['item']->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($row['on_hand'], 3), '0'), '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format($row['tied_up'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-body-sm text-on-surface-variant">
                                    {{ $row['last_sold'] ? \Illuminate\Support\Carbon::parse($row['last_sold'])->translatedFormat('j M Y') : __('لم يُبَع قط') }}
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($slowMovers as $row)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $row['item']->name }}</p>
                                        <p class="font-body-sm text-outline">{{ $row['last_sold'] ? \Illuminate\Support\Carbon::parse($row['last_sold'])->translatedFormat('j M Y') : __('لم يُبَع قط') }}</p>
                                    </div>
                                    <span class="whitespace-nowrap font-numeral-lg font-bold" dir="ltr">{{ number_format($row['tied_up'], 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            @endif
        </x-card>

        @if ($variance->isNotEmpty())
            <x-card title="{{ __('الفرق بين الاستهلاك النظري والفعلي') }}">
                <p class="mb-5 font-body-sm text-on-surface-variant">
                    {{ __('الوصفة تقول إن مئة ساندويش تأخذ عشرة كيلو جبنة، والدفتر يقول إن اثني عشر خرجت. هذا الفرق هو الرقم الوحيد هنا الذي يتصرّف فيه المطبخ في نفس اليوم.') }}
                </p>

                <x-table-responsive :headers="[
                    __('المكوّن'),
                    ['label' => __('النظري'), 'align' => 'end'],
                    ['label' => __('الفعلي'), 'align' => 'end'],
                    ['label' => __('الفرق'), 'align' => 'end'],
                    ['label' => __('كلفة الفرق'), 'align' => 'end'],
                ]">
                    <x-slot:table>
                        @foreach ($variance as $row)
                            <tr class="transition-colors hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $row['item']->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($row['theoretical'], 3), '0'), '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($row['actual'], 3), '0'), '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold {{ $row['variance'] > 0 ? 'text-error' : 'text-success' }}" dir="ltr">
                                    {{ $row['variance'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($row['variance'], 3), '0'), '.') }}
                                    @if ($row['variance_percent'] !== null)
                                        <span class="block font-body-sm">{{ $row['variance_percent'] }}%</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg {{ $row['cost'] > 0 ? 'text-error' : 'text-success' }}" dir="ltr">{{ number_format($row['cost'], 2) }}</td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($variance as $row)
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-body-lg font-bold">{{ $row['item']->name }}</p>
                                    <span class="whitespace-nowrap font-numeral-lg font-bold {{ $row['variance'] > 0 ? 'text-error' : 'text-success' }}" dir="ltr">
                                        {{ $row['variance'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($row['variance'], 3), '0'), '.') }}
                                    </span>
                                </div>
                                <p class="mt-2 font-body-sm text-outline" dir="ltr">{{ number_format($row['cost'], 2) }}</p>
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            </x-card>
        @endif

        @if ($valuation['rows']->isNotEmpty())
            <x-card title="{{ __('تفصيل التقييم') }}">
                <x-table-responsive :headers="[
                    $naming->item(),
                    ['label' => __('الرصيد'), 'align' => 'end'],
                    ['label' => __('تكلفة الوحدة'), 'align' => 'end'],
                    ['label' => __('القيمة'), 'align' => 'end'],
                    ['label' => __('صافي القيمة القابلة للتحقّق'), 'align' => 'end'],
                    ['label' => __('التخفيض'), 'align' => 'end'],
                ]">
                    <x-slot:table>
                        @foreach ($valuation['rows'] as $row)
                            <tr class="transition-colors hover:bg-surface-container-low/50 {{ $row['impaired'] ? 'bg-error-container/5' : '' }}">
                                <td class="px-4 py-3 font-body-sm font-bold">{{ $row['item']->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ rtrim(rtrim(number_format($row['on_hand'], 3), '0'), '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg" dir="ltr">{{ number_format($row['unit_cost'], 3) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg font-bold" dir="ltr">{{ number_format($row['value'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg text-on-surface-variant" dir="ltr">{{ $row['realisable'] > 0 ? number_format($row['realisable'], 3) : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-numeral-lg {{ $row['impaired'] ? 'font-bold text-error' : 'text-outline' }}" dir="ltr">
                                    {{ $row['impaired'] ? number_format($row['write_down'], 2) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:table>

                    <x-slot:cards>
                        @foreach ($valuation['rows'] as $row)
                            <div class="rounded-2xl border p-4 {{ $row['impaired'] ? 'border-error/30 bg-error-container/5' : 'border-outline-variant bg-surface-container-lowest' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-body-lg font-bold">{{ $row['item']->name }}</p>
                                        <p class="font-body-sm text-outline" dir="ltr">{{ rtrim(rtrim(number_format($row['on_hand'], 3), '0'), '.') }} × {{ number_format($row['unit_cost'], 3) }}</p>
                                    </div>
                                    <span class="whitespace-nowrap font-numeral-lg font-bold" dir="ltr">{{ number_format($row['value'], 2) }}</span>
                                </div>
                                @if ($row['impaired'])
                                    <p class="mt-2 font-body-sm font-bold text-error" dir="ltr">−{{ number_format($row['write_down'], 2) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </x-slot:cards>
                </x-table-responsive>
            </x-card>
        @endif
    </div>
@endsection
