<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\InvoiceService;
use App\Support\InvoiceQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class InvoiceController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canIssue()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية للفوترة.'));
        }

        $perPage = $this->perPage($request, 20);

        return view('invoices.index', [
            'invoices' => $this->invoices->list($request->query('type'), $request->query('search'), $perPage),
            'summary' => $this->invoices->summary(),
            'configured' => $this->invoices->isConfigured(),
            'type' => $request->query('type'),
            'search' => $request->query('search'),
            'perPage' => $perPage,
        ]);
    }

    public function show(string $invoice): View|RedirectResponse
    {
        if (! $this->canIssue()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية للفوترة.'));
        }

        $record = Invoice::with(['lines', 'transfer', 'saleReturn', 'teamMember'])->findOrFail($invoice);

        return view('invoices.show', [
            'invoice' => $record,

            // Decoded rather than displayed raw: a payload nobody has read back
            // is a payload nobody knows is right.
            'qr' => InvoiceQr::decode((string) $record->qr_payload),
        ]);
    }

    /**
     * The UBL document itself. This is the file a clearance provider or an
     * auditor is handed - everything else on the screen is a rendering of it.
     */
    public function xml(string $invoice): Response|RedirectResponse
    {
        if (! $this->canIssue()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية للفوترة.'));
        }

        $record = Invoice::findOrFail($invoice);

        return response((string) $record->xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$record->number.'.xml"',
        ]);
    }

    public function issue(Request $request, string $transfer): RedirectResponse
    {
        abort_unless($this->canIssue(), 403);

        $data = $request->validate([
            'buyerName' => ['nullable', 'string', 'max:255'],
            'buyerTaxNumber' => ['nullable', 'string', 'max:64'],
            'buyerAddress' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $invoice = $this->invoices->issueForSale(Transfer::findOrFail($transfer), [
                'name' => $data['buyerName'] ?? null,
                'taxNumber' => $data['buyerTaxNumber'] ?? null,
                'address' => $data['buyerAddress'] ?? null,
            ]);
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice->id)->with('success', __('صدرت الفاتورة :number.', ['number' => $invoice->number]));
    }

    public function credit(string $saleReturn): RedirectResponse
    {
        abort_unless($this->canIssue(), 403);

        try {
            $invoice = $this->invoices->issueForReturn(SaleReturn::findOrFail($saleReturn));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice->id)->with('success', __('صدر إشعار دائن :number.', ['number' => $invoice->number]));
    }

    /**
     * Re-hash every stored document and compare. Cheap, and the only way the
     * chain is worth having: an unverified chain proves nothing.
     */
    public function verify(): View|RedirectResponse
    {
        if (! $this->canIssue()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية للفوترة.'));
        }

        $results = $this->invoices->verifyChain();

        return view('invoices.verify', [
            'results' => $results,
            'broken' => $results->reject(fn (array $row) => $row['valid'])->count(),
        ]);
    }

    public function settings(): View|RedirectResponse
    {
        if (! $this->canConfigure()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لضبط بيانات الفوترة.'));
        }

        return view('invoices.settings', ['settings' => $this->invoices->sellerSettings()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        abort_unless($this->canConfigure(), 403);

        $data = $request->validate([
            'sellerName' => ['required', 'string', 'max:255'],
            'sellerTaxNumber' => ['nullable', 'string', 'max:64'],
            'sellerAddress' => ['nullable', 'string', 'max:255'],

            // ISO 4217. Three letters, and frozen onto every invoice issued
            // afterwards - changing it later does not rewrite what was sent.
            'currency' => ['required', 'string', Rule::in(['ILS', 'JOD', 'USD', 'EUR', 'SAR', 'AED', 'EGP'])],
        ], [
            'sellerName.required' => __('اسم البائع مطلوب — فاتورة لا تقول من أصدرها ليست فاتورة.'),
        ]);

        $this->invoices->saveSellerSettings([
            InvoiceService::SELLER_NAME => $data['sellerName'],
            InvoiceService::SELLER_TAX_NUMBER => $data['sellerTaxNumber'] ?? null,
            InvoiceService::SELLER_ADDRESS => $data['sellerAddress'] ?? null,
            InvoiceService::CURRENCY => $data['currency'],
        ]);

        return redirect()->route('invoices.settings')->with('success', __('حُفظت بيانات البائع.'));
    }

    private function canIssue(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::TransfersCreate);
    }

    private function canConfigure(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::ConstantsManage);
    }
}
