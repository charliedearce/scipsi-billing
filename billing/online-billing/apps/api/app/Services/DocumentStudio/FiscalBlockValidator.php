<?php

namespace App\Services\DocumentStudio;

class FiscalBlockValidator
{
    /**
     * Mandatory bound fields for BIR compliance on Sales Invoices.
     */
    public const REQUIRED_INVOICE_FIELDS = [
        'invoice.invoice_number' => 'Sequential Invoice Number',
        'invoice.invoice_date' => 'Invoice Issuance Date',
        'issuer.registered_name' => 'Issuer Registered Business Name',
        'issuer.tin' => 'Issuer Tax Identification Number',
        'issuer.address' => 'Issuer Registered Address',
        'buyer.registered_name' => 'Buyer Registered Business Name',
        'buyer.tin' => 'Buyer Tax Identification Number',
        'buyer.address' => 'Buyer Billing Address',
        'totals.vatable_sales' => 'VATable Sales Base',
        'totals.vat_amount' => 'Value Added Tax (12%)',
        'totals.total_amount_due' => 'Grand Total Amount Due',
    ];

    /**
     * Validate layout against BIR fiscal requirements.
     *
     * @param  array<string, mixed>  $layout
     * @param  string  $documentKind  SERVICE, SERVICE_NSCL, PPA, etc.
     * @return array{is_valid: bool, missing_fields: array<string>, missing_elements: array<string>, errors: array<string>}
     */
    public function validateFiscalBlocks(array $layout, string $documentKind): array
    {
        $missingFields = [];
        $missingElements = [];
        $errors = [];

        // Receipts have different fiscal rules; invoices require full BIR invoice layout
        $isInvoice = in_array($documentKind, ['SERVICE', 'SERVICE_NSCL', 'PPA'], true);

        if (! $isInvoice) {
            return [
                'is_valid' => true,
                'missing_fields' => [],
                'missing_elements' => [],
                'errors' => [],
            ];
        }

        // Collect all bound fields, static text, and tables from all bands
        $boundFields = [];
        $hasItemTable = false;
        $allStaticText = '';

        foreach ($layout['bands'] ?? [] as $bandName => $band) {
            foreach ($band['elements'] ?? [] as $element) {
                $type = $element['type'] ?? '';

                // Check visibility: an element with visible === false cannot satisfy fiscal requirements
                if (isset($element['visible']) && $element['visible'] === false) {
                    continue;
                }

                // Check clipping / non-zero dimensions
                $width = (float) ($element['width_mm'] ?? 0);
                $height = (float) ($element['height_mm'] ?? 0);
                if ($width <= 0 || $height <= 0) {
                    continue;
                }

                if ($type === 'bound_text' && ! empty($element['field'])) {
                    $boundFields[$element['field']] = true;
                } elseif ($type === 'static_text' && ! empty($element['text'])) {
                    $allStaticText .= ' '.$element['text'];
                } elseif ($type === 'table') {
                    $hasItemTable = true;
                }
            }
        }

        // 1. Check required fields
        foreach (self::REQUIRED_INVOICE_FIELDS as $field => $label) {
            if (! isset($boundFields[$field])) {
                $missingFields[] = $field;
                $errors[] = "Missing required fiscal field: {$label} [{$field}].";
            }
        }

        // 2. Check item table
        if (! $hasItemTable) {
            $missingElements[] = 'item_table';
            $errors[] = 'A line items table is required in the details band.';
        }

        // 3. Check statutory disclaimer notice
        if (! preg_match('/NOT VALID FOR CLAIM OF INPUT TAX|BIR PERMIT|CAS PERMIT|THIS DOCUMENT/i', $allStaticText)) {
            $missingElements[] = 'statutory_disclaimer';
            $errors[] = 'Missing mandatory statutory fiscal disclaimer or BIR permit notice in layout text.';
        }

        return [
            'is_valid' => empty($errors),
            'missing_fields' => $missingFields,
            'missing_elements' => $missingElements,
            'errors' => $errors,
        ];
    }
}
