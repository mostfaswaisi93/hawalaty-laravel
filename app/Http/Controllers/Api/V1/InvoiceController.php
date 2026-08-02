<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Throwable;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            $this->invoices->list($request->query('type'), $request->query('search'), 20)
        );
    }

    public function show(string $invoice): JsonResponse
    {
        return (new InvoiceResource(Invoice::with('lines')->findOrFail($invoice)))->response();
    }

    /**
     * The UBL document itself, as XML rather than wrapped in JSON.
     *
     * This is the artefact a clearance provider consumes; base64-ing it into a
     * JSON envelope would only give the caller something to undo.
     */
    public function xml(string $invoice): Response
    {
        $record = Invoice::findOrFail($invoice);

        return response((string) $record->xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function issue(Request $request, string $transfer): JsonResponse
    {
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
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new InvoiceResource($invoice->load('lines')))->response()->setStatusCode(201);
    }

    /**
     * Re-hash every stored document and compare.
     *
     * Exposed so an external monitor can check the chain on a schedule without
     * anyone opening a screen - an unverified chain proves nothing.
     */
    public function verify(): JsonResponse
    {
        $results = $this->invoices->verifyChain();

        return response()->json([
            'data' => [
                'documents' => $results->count(),
                'broken' => $results->reject(fn (array $row) => $row['valid'])->count(),
                'intact' => $results->every(fn (array $row) => $row['valid']),
                'rows' => $results->map(fn (array $row) => [
                    'number' => $row['invoice']->number,
                    'valid' => $row['valid'],
                ]),
            ],
        ]);
    }
}
