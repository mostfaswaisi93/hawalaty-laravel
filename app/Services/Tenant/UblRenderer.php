<?php

namespace App\Services\Tenant;

use App\Enums\TaxCategory;
use App\Models\Tenant\Invoice;
use DOMDocument;
use DOMElement;

/**
 * EN 16931 rendered as UBL 2.1.
 *
 * A pure transformation - an invoice in, XML out, nothing else touched. That is
 * deliberate: this is the part a tax authority validates, so it has to be
 * inspectable and testable on its own rather than tangled with how the invoice
 * came to exist.
 *
 * Built with DOM rather than string concatenation. An invoice carrying an
 * ampersand in a customer's name, or Arabic text in a seller's, has to come out
 * as well-formed XML every time, and hand-written escaping is how that fails.
 */
class UblRenderer
{
    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const NS_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /**
     * The EN 16931 conformance identifier. Says which rule set a validator
     * should hold the document to.
     */
    private const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017';

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing('lines');

        $creditNote = $invoice->isCreditNote();
        $rootName = $creditNote ? 'CreditNote' : 'Invoice';

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElementNS($creditNote ? self::NS_CREDIT_NOTE : self::NS_INVOICE, $rootName);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $document->appendChild($root);

        $this->header($document, $root, $invoice);
        $this->parties($document, $root, $invoice);
        $this->allowance($document, $root, $invoice);
        $this->taxTotal($document, $root, $invoice);
        $this->monetaryTotals($document, $root, $invoice);
        $this->lines($document, $root, $invoice, $creditNote);

        return (string) $document->saveXML();
    }

    private function header(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $this->cbc($document, $root, 'CustomizationID', self::CUSTOMIZATION_ID);
        $this->cbc($document, $root, 'ID', $invoice->number);
        $this->cbc($document, $root, 'UUID', $invoice->id);
        $this->cbc($document, $root, 'IssueDate', $invoice->issued_at->format('Y-m-d'));
        $this->cbc($document, $root, 'IssueTime', $invoice->issued_at->format('H:i:s'));

        // UNCL 1001: 388 commercial invoice, 381 credit note.
        $this->cbc($document, $root, $invoice->isCreditNote() ? 'CreditNoteTypeCode' : 'InvoiceTypeCode', $invoice->type->documentTypeCode());

        $this->cbc($document, $root, 'DocumentCurrencyCode', $invoice->currency_code);
        $this->cbc($document, $root, 'TaxCurrencyCode', $invoice->currency_code);

        // The chain, carried in the document rather than only in the database:
        // a file handed to an auditor has to be checkable on its own.
        if (filled($invoice->previous_hash)) {
            $reference = $this->cac($document, $root, 'AdditionalDocumentReference');
            $this->cbc($document, $reference, 'ID', 'PIH');

            $attachment = $this->cac($document, $reference, 'Attachment');
            $embedded = $this->cbc($document, $attachment, 'EmbeddedDocumentBinaryObject', $invoice->previous_hash);
            $embedded->setAttribute('mimeCode', 'text/plain');
        }

        // A credit note has to name what it reverses, or it is just a negative
        // invoice from nowhere.
        if ($invoice->isCreditNote() && $invoice->saleReturn?->transfer?->id !== null) {
            $billing = $this->cac($document, $root, 'BillingReference');
            $line = $this->cac($document, $billing, 'InvoiceDocumentReference');
            $this->cbc($document, $line, 'ID', (string) $invoice->saleReturn->transfer->id);
        }
    }

    private function parties(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $supplier = $this->cac($document, $root, 'AccountingSupplierParty');
        $this->party($document, $supplier, $invoice->seller_name, $invoice->seller_tax_number, $invoice->seller_address);

        $customer = $this->cac($document, $root, 'AccountingCustomerParty');
        $this->party($document, $customer, $invoice->buyer_name, $invoice->buyer_tax_number, $invoice->buyer_address);
    }

    private function party(DOMDocument $document, DOMElement $parent, ?string $name, ?string $taxNumber, ?string $address): void
    {
        $party = $this->cac($document, $parent, 'Party');

        if (filled($address)) {
            $postal = $this->cac($document, $party, 'PostalAddress');
            $this->cbc($document, $postal, 'StreetName', $address);
        }

        // Only present when there is one. An empty tax number is worse than a
        // missing element - it asserts the party has none.
        if (filled($taxNumber)) {
            $scheme = $this->cac($document, $party, 'PartyTaxScheme');
            $this->cbc($document, $scheme, 'CompanyID', $taxNumber);

            $taxScheme = $this->cac($document, $scheme, 'TaxScheme');
            $this->cbc($document, $taxScheme, 'ID', 'VAT');
        }

        $legal = $this->cac($document, $party, 'PartyLegalEntity');
        $this->cbc($document, $legal, 'RegistrationName', $name ?: '-');
    }

    private function allowance(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        if ((float) $invoice->allowance_total <= 0) {
            return;
        }

        $allowance = $this->cac($document, $root, 'AllowanceCharge');

        // false = an allowance (a discount); true would be a charge.
        $this->cbc($document, $allowance, 'ChargeIndicator', 'false');
        $this->cbc($document, $allowance, 'AllowanceChargeReason', 'Discount');
        $this->money($document, $allowance, 'Amount', (float) $invoice->allowance_total, $invoice->currency_code);
    }

    private function taxTotal(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $total = $this->cac($document, $root, 'TaxTotal');
        $this->money($document, $total, 'TaxAmount', (float) $invoice->tax_amount, $invoice->currency_code);

        // One subtotal per category and rate. Reporting per line would be
        // rejected: the standard groups them.
        foreach ($invoice->taxBreakdown() as $row) {
            $subtotal = $this->cac($document, $total, 'TaxSubtotal');
            $this->money($document, $subtotal, 'TaxableAmount', $row['taxable'], $invoice->currency_code);
            $this->money($document, $subtotal, 'TaxAmount', $row['tax'], $invoice->currency_code);

            $this->taxCategory($document, $subtotal, $row['category'], $row['rate']);
        }
    }

    private function taxCategory(DOMDocument $document, DOMElement $parent, string $category, float $rate): void
    {
        $case = TaxCategory::tryFrom($category) ?? TaxCategory::Standard;

        $element = $this->cac($document, $parent, 'TaxCategory');
        $this->cbc($document, $element, 'ID', $case->value);
        $this->cbc($document, $element, 'Percent', number_format($rate, 2, '.', ''));

        // A category other than the standard rate must say why nothing was
        // charged - the standard treats a silent zero as an error.
        if (! $case->chargesTax()) {
            $this->cbc($document, $element, 'TaxExemptionReasonCode', $case->value);
            $this->cbc($document, $element, 'TaxExemptionReason', (string) $case->exemptionReason());
        }

        $scheme = $this->cac($document, $element, 'TaxScheme');
        $this->cbc($document, $scheme, 'ID', 'VAT');
    }

    private function monetaryTotals(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $totals = $this->cac($document, $root, 'LegalMonetaryTotal');
        $currency = $invoice->currency_code;

        $this->money($document, $totals, 'LineExtensionAmount', (float) $invoice->line_extension_amount, $currency);
        $this->money($document, $totals, 'TaxExclusiveAmount', (float) $invoice->tax_exclusive_amount, $currency);
        $this->money($document, $totals, 'TaxInclusiveAmount', (float) $invoice->tax_inclusive_amount, $currency);

        if ((float) $invoice->allowance_total > 0) {
            $this->money($document, $totals, 'AllowanceTotalAmount', (float) $invoice->allowance_total, $currency);
        }

        $this->money($document, $totals, 'PayableAmount', (float) $invoice->payable_amount, $currency);
    }

    private function lines(DOMDocument $document, DOMElement $root, Invoice $invoice, bool $creditNote): void
    {
        foreach ($invoice->lines as $line) {
            $element = $this->cac($document, $root, $creditNote ? 'CreditNoteLine' : 'InvoiceLine');

            $this->cbc($document, $element, 'ID', (string) $line->position);

            $quantity = $this->cbc(
                $document,
                $element,
                $creditNote ? 'CreditedQuantity' : 'InvoicedQuantity',
                number_format((float) $line->quantity, 3, '.', ''),
            );

            // UN/CEFACT Rec 20 - mandatory, and the reason units are an enum
            // rather than free text everywhere else in the system.
            $quantity->setAttribute('unitCode', $line->unit_code);

            $this->money($document, $element, 'LineExtensionAmount', (float) $line->line_extension_amount, $invoice->currency_code);

            $taxTotal = $this->cac($document, $element, 'TaxTotal');
            $this->money($document, $taxTotal, 'TaxAmount', (float) $line->tax_amount, $invoice->currency_code);
            $this->money($document, $taxTotal, 'RoundingAmount', $line->grossAmount(), $invoice->currency_code);

            $item = $this->cac($document, $element, 'Item');
            $this->cbc($document, $item, 'Name', $line->name);
            $this->taxCategory($document, $item, (string) $line->tax_category, (float) $line->tax_rate);

            $price = $this->cac($document, $element, 'Price');
            $this->money($document, $price, 'PriceAmount', (float) $line->unit_price, $invoice->currency_code);
        }
    }

    private function cac(DOMDocument $document, DOMElement $parent, string $name): DOMElement
    {
        $element = $document->createElementNS(self::NS_CAC, 'cac:'.$name);
        $parent->appendChild($element);

        return $element;
    }

    private function cbc(DOMDocument $document, DOMElement $parent, string $name, string $value): DOMElement
    {
        // createElementNS does not escape its value argument, so the text node
        // is added separately - this is where an ampersand in a name would
        // otherwise produce broken XML.
        $element = $document->createElementNS(self::NS_CBC, 'cbc:'.$name);
        $element->appendChild($document->createTextNode($value));
        $parent->appendChild($element);

        return $element;
    }

    private function money(DOMDocument $document, DOMElement $parent, string $name, float $amount, string $currency): void
    {
        $element = $this->cbc($document, $parent, $name, number_format($amount, 2, '.', ''));
        $element->setAttribute('currencyID', $currency);
    }
}
