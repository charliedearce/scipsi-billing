<?php

namespace App\Services\DocumentStudio;

use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentRendererService
{
    public function __construct(protected DocumentTemplateAssetService $assetService) {}

    /**
     * Render layout + data to binary PDF string.
     *
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $data
     * @return string Binary PDF string
     */
    public function renderToPdf(array $layout, array $data, ?int $organizationId = null): string
    {
        $html = $this->compileHtml($layout, $data, $organizationId);

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false); // Sandbox security
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);

        $paperSize = strtolower((string) ($layout['page']['paper_size'] ?? 'letter'));
        $orientation = strtolower((string) ($layout['page']['orientation'] ?? 'portrait'));
        $dompdf->setPaper($paperSize, $orientation);

        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Compile layout definition + bound data dictionary into print-accurate HTML/CSS.
     */
    public function compileHtml(array $layout, array $data, ?int $organizationId = null): string
    {
        $page = $layout['page'] ?? [];
        $margins = $page['margins'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];
        $topMargin = (float) ($margins['top'] ?? 10);
        $rightMargin = (float) ($margins['right'] ?? 10);
        $bottomMargin = (float) ($margins['bottom'] ?? 10);
        $leftMargin = (float) ($margins['left'] ?? 10);

        $bands = $layout['bands'] ?? null;
        if (! is_array($bands)) {
            throw new \InvalidArgumentException('Layout definition is missing valid bands configuration.');
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<style>';
        $html .= "@page { margin: {$topMargin}mm {$rightMargin}mm {$bottomMargin}mm {$leftMargin}mm; }";
        $html .= 'body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #111; margin: 0; padding: 0; }';
        $html .= '.band { position: relative; width: 100%; }';
        $html .= '.element { position: absolute; box-sizing: border-box; }';
        $html .= 'table.items-table { width: 100%; border-collapse: collapse; margin-top: 5px; font-size: 8.5pt; }';
        $html .= 'table.items-table th { border-bottom: 1.5px solid #222; padding: 4px 6px; text-align: left; font-size: 8pt; text-transform: uppercase; }';
        $html .= 'table.items-table td { border-bottom: 0.5px solid #ddd; padding: 4px 6px; }';
        $html .= '.text-right { text-align: right; }';
        $html .= '.text-center { text-align: center; }';
        $html .= '.bold { font-weight: bold; }';
        $html .= '</style></head><body>';

        // 1. Header Band
        $headerBand = $bands['header'] ?? null;
        if ($headerBand) {
            $headerHeight = (float) ($headerBand['height_mm'] ?? 55);
            $html .= "<div class=\"band header-band\" style=\"height: {$headerHeight}mm;\">";
            $html .= $this->renderBandElements($headerBand['elements'] ?? [], $data, $organizationId);
            $html .= '</div>';
        }

        // 2. Details Band (Line Items)
        $detailsBand = $bands['details'] ?? null;
        if ($detailsBand) {
            $html .= '<div class="details-band">';
            $html .= $this->renderBandElements($detailsBand['elements'] ?? [], $data, $organizationId);
            $html .= '</div>';
        }

        // 3. Summary Band
        $summaryBand = $bands['summary'] ?? null;
        if ($summaryBand) {
            $summaryHeight = (float) ($summaryBand['height_mm'] ?? 40);
            $html .= "<div class=\"band summary-band\" style=\"height: {$summaryHeight}mm; margin-top: 10px;\">";
            $html .= $this->renderBandElements($summaryBand['elements'] ?? [], $data, $organizationId);
            $html .= '</div>';
        }

        // 4. Footer Band
        $footerBand = $bands['footer'] ?? null;
        if ($footerBand) {
            $footerHeight = (float) ($footerBand['height_mm'] ?? 25);
            $html .= "<div class=\"band footer-band\" style=\"height: {$footerHeight}mm; margin-top: 10px;\">";
            $html .= $this->renderBandElements($footerBand['elements'] ?? [], $data, $organizationId);
            $html .= '</div>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Render individual elements inside a band.
     */
    private function renderBandElements(array $elements, array $data, ?int $organizationId): string
    {
        $out = '';
        foreach ($elements as $el) {
            $type = $el['type'] ?? '';
            $x = (float) ($el['x_mm'] ?? 0);
            $y = (float) ($el['y_mm'] ?? 0);
            $w = (float) ($el['width_mm'] ?? 50);
            $h = (float) ($el['height_mm'] ?? 10);
            $style = "left: {$x}mm; top: {$y}mm; width: {$w}mm; height: {$h}mm;";

            if (isset($el['font_size_pt'])) {
                $style .= " font-size: {$el['font_size_pt']}pt;";
            }
            if (! empty($el['font_weight'])) {
                $style .= " font-weight: {$el['font_weight']};";
            }
            if (! empty($el['align'])) {
                $style .= " text-align: {$el['align']};";
            }
            if (! empty($el['color'])) {
                $style .= " color: {$el['color']};";
            }

            if ($type === 'static_text') {
                $text = nl2br(htmlspecialchars((string) ($el['text'] ?? ''), ENT_QUOTES, 'UTF-8'), false);
                $out .= "<div class=\"element\" style=\"{$style}\">{$text}</div>";
            } elseif ($type === 'bound_text') {
                $val = $this->resolveDataField($data, (string) ($el['field'] ?? ''));
                $formatted = htmlspecialchars((string) $val);
                $out .= "<div class=\"element\" style=\"{$style}\">{$formatted}</div>";
            } elseif ($type === 'line') {
                $out .= "<div class=\"element\" style=\"{$style} border-bottom: 1px solid black;\"></div>";
            } elseif ($type === 'rectangle') {
                $out .= "<div class=\"element\" style=\"{$style} border: 1px solid #333;\"></div>";
            } elseif ($type === 'image') {
                if (! $organizationId || ! isset($el['asset_id'])) {
                    throw new \InvalidArgumentException('Image elements require an organization-scoped branding asset.');
                }
                $source = htmlspecialchars($this->assetService->renderDataUri((int) $el['asset_id'], $organizationId), ENT_QUOTES, 'UTF-8');
                $out .= "<img class=\"element\" src=\"{$source}\" style=\"{$style} object-fit: contain;\" alt=\"\">";
            } elseif ($type === 'table') {
                $out .= $this->renderTableElement($el, $data);
            }
        }

        return $out;
    }

    /**
     * Render tabular line items.
     */
    private function renderTableElement(array $element, array $data): string
    {
        $columns = $element['columns'] ?? [];
        $items = $data['items'] ?? [];

        $html = '<table class="items-table"><thead><tr>';
        foreach ($columns as $col) {
            $align = $col['align'] ?? 'left';
            $w = isset($col['width_pct']) ? "width: {$col['width_pct']}%;" : '';
            $html .= "<th class=\"text-{$align}\" style=\"{$w}\">".htmlspecialchars($col['label'] ?? '').'</th>';
        }
        $html .= '</tr></thead><tbody>';

        if (empty($items)) {
            $html .= '<tr><td colspan="'.count($columns).'" class="text-center">No billable items</td></tr>';
        } else {
            foreach ($items as $item) {
                $html .= '<tr>';
                foreach ($columns as $col) {
                    $align = $col['align'] ?? 'left';
                    $field = $col['field'] ?? '';
                    $val = $item[$field] ?? '';
                    $html .= "<td class=\"text-{$align}\">".htmlspecialchars((string) $val).'</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Resolve dot-notated field path from bound dataset.
     */
    public function resolveDataField(array $data, string $fieldPath): mixed
    {
        $parts = explode('.', $fieldPath);
        $current = $data;
        foreach ($parts as $part) {
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return '';
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Build sample dataset for studio preview.
     */
    public function createSampleData(string $documentKind = 'SERVICE'): array
    {
        if ($documentKind === 'ACCOUNT_STATEMENT') {
            return [
                'organization' => ['name' => 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.'],
                'statement' => ['statement_number' => 'SOA-0000001234', 'as_of_date' => date('Y-m-d'), 'currency' => 'PHP'],
                'customer' => ['name' => 'SAMPLE CUSTOMER, INC.'],
                'totals' => ['outstanding_total' => '8,400.00'],
                'items' => [
                    ['invoice_number' => 'SI-0000000123', 'business_date' => date('Y-m-d'), 'invoice_amount' => '10,000.00', 'payment_amount' => '1,600.00', 'outstanding_amount' => '8,400.00'],
                ],
            ];
        }

        if (in_array($documentKind, ['YELLOW_INVOICE', 'WHITE_RECEIPT'], true)) {
            $isYellow = $documentKind === 'YELLOW_INVOICE';

            return [
                'organization' => ['name' => 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.'],
                'transmittal' => [
                    'transmittal_number' => $isYellow ? 'YTR-0000001234' : 'WTR-0000001234',
                    'kind_label' => $isYellow ? 'YELLOW INVOICE TRANSMITTAL' : 'WHITE RECEIPT TRANSMITTAL',
                    'as_of_date' => date('Y-m-d'),
                    'currency' => 'PHP',
                ],
                'summary' => ['primary_total' => $isYellow ? '11,200.00' : '10,000.00'],
                'items' => [
                    ['source_number' => $isYellow ? 'SI-0000000123' : 'CR-0000000456', 'business_date' => date('Y-m-d'), 'party_name' => 'SAMPLE CUSTOMER, INC.', 'amount' => $isYellow ? '11,200.00' : '10,000.00'],
                ],
            ];
        }

        if (in_array($documentKind, ['COLLECTION_RECEIPT', 'ACKNOWLEDGEMENT_RECEIPT'], true)) {
            $isAck = $documentKind === 'ACKNOWLEDGEMENT_RECEIPT';

            return [
                'receipt' => [
                    'receipt_number' => $isAck ? 'ACK-0000000001' : 'CR-0000000001',
                    'receipt_date' => date('Y-m-d'),
                    'currency' => 'PHP',
                    'series_code' => $isAck ? 'ACK-GENSAN-2026' : 'CR-GENSAN-2026',
                    'receipt_kind' => $isAck ? 'ACKNOWLEDGEMENT' : 'OFFICIAL',
                    'counts_as_official_receipt' => ! $isAck,
                    'document_title' => $isAck ? 'ACKNOWLEDGEMENT RECEIPT' : 'COLLECTION RECEIPT / OFFICIAL RECEIPT',
                    'fiscal_notice' => $isAck
                        ? 'This acknowledgement receipt records internal settlement only. It is not an Official Receipt and must not be treated as a BIR fiscal OR issuance.'
                        : 'This collection receipt / official receipt records verified settlement.',
                ],
                'issuer' => [
                    'registered_name' => 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.',
                    'tin' => '000-123-456-000',
                    'address' => 'Makar Wharf, General Santos City, South Cotabato, Philippines',
                    'bir_permit' => $isAck ? '' : 'BIR-CAS-2026-00129-GENSAN',
                ],
                'payer' => [
                    'registered_name' => 'SAMPLE CUSTOMER, INC.',
                    'tin' => '111-222-333-000',
                ],
                'totals' => [
                    'cash_received' => '10,000.00',
                    'withholding_received' => '0.00',
                    'applied_amount' => '10,000.00',
                    'unapplied_amount' => '0.00',
                ],
                'items' => [
                    ['invoice_number' => 'SI-0000000123', 'cash_applied' => '10,000.00', 'withholding_applied' => '0.00', 'applied_amount' => '10,000.00'],
                ],
            ];
        }

        return [
            'invoice' => [
                'invoice_number' => 'SI-0000001234',
                'invoice_date' => date('Y-m-d'),
                'period' => date('Y-m'),
                'reference' => 'SAMPLE-REF-2026',
            ],
            'issuer' => [
                'registered_name' => 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.',
                'trade_name' => 'SCIPSI PORT TERMINAL',
                'tin' => '000-123-456-000',
                'address' => 'Makar Wharf, General Santos City, South Cotabato, Philippines',
                'bir_permit' => 'BIR-CAS-2026-00129-GENSAN',
            ],
            'buyer' => [
                'registered_name' => 'DOLE PHILIPPINES, INCORPORATED',
                'trade_name' => 'DOLE PHILS',
                'tin' => '123-456-789-0000',
                'branch_code' => '0000',
                'address' => 'Cannery Site, Polomolok, South Cotabato',
            ],
            'shipment' => [
                'vessel_name' => 'HONDURAS',
                'voyage' => '102',
                'movement' => 'IN',
                'route' => 'Domestic',
                'notes' => 'Sample notes',
            ],
            'totals' => [
                'vatable_sales' => '10,000.00',
                'zero_rated_sales' => '0.00',
                'vat_exempt_sales' => '0.00',
                'vat_amount' => '1,200.00',
                'ppa_share_amount' => '1,000.00',
                'fuel_surcharge_amount' => '500.00',
                'total_amount_due' => '11,200.00',
            ],
            'items' => [
                [
                    'quantity' => '10.0000',
                    'unit' => 'BOX',
                    'service_name' => 'Arrastre - Domestic Breakbulk Cargo',
                    'rate' => '1,000.0000',
                    'gross_amount' => '10,000.00',
                    'fuel_surcharge' => '500.00',
                    'vat_amount' => '1,200.00',
                    'total_amount' => '11,200.00',
                ],
            ],
        ];
    }
}
