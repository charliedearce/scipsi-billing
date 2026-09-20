<?php

namespace App\Services\DocumentStudio;

use Illuminate\Validation\ValidationException;

class DocumentLayoutSchema
{
    public const SCHEMA_VERSION = '1.0.0';

    public const ALLOWED_ELEMENT_TYPES = [
        'static_text',
        'bound_text',
        'line',
        'rectangle',
        'table',
        'image',
        'qrcode',
    ];

    public const ALLOWED_BANDS = [
        'header',
        'details',
        'summary',
        'footer',
    ];

    public const ALLOWED_PAPER_SIZES = [
        'LETTER', // 215.9 x 279.4 mm
        'A4',     // 210 x 297 mm
        'CUSTOM',
    ];

    public const ALLOWED_FONTS = [
        'Helvetica',
        'Times-Roman',
        'Courier',
    ];

    /**
     * Validate the structural integrity of a layout definition array.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed> Validated layout
     *
     * @throws ValidationException
     */
    public function validateStructure(array $layout): array
    {
        $errors = [];

        // 1. Page Configuration
        if (! isset($layout['page']) || ! is_array($layout['page'])) {
            $errors['page'] = 'The layout must contain a page configuration object.';
        } else {
            $page = $layout['page'];
            $paperSize = $page['paper_size'] ?? 'LETTER';
            if (! in_array($paperSize, self::ALLOWED_PAPER_SIZES, true)) {
                $errors['page.paper_size'] = 'Invalid paper size: '.$paperSize;
            }

            $orientation = $page['orientation'] ?? 'PORTRAIT';
            if (! in_array(strtoupper((string) $orientation), ['PORTRAIT', 'LANDSCAPE'], true)) {
                $errors['page.orientation'] = 'Orientation must be PORTRAIT or LANDSCAPE.';
            }

            if (! isset($page['margins']) || ! is_array($page['margins'])) {
                $errors['page.margins'] = 'Page margins object is required (top, right, bottom, left).';
            }
        }

        // 2. Bands Configuration
        if (! isset($layout['bands']) || ! is_array($layout['bands'])) {
            $errors['bands'] = 'The layout must define a bands object.';
        } else {
            foreach ($layout['bands'] as $bandName => $bandConfig) {
                if (! in_array($bandName, self::ALLOWED_BANDS, true)) {
                    $errors["bands.{$bandName}"] = "Unsupported band: {$bandName}";

                    continue;
                }

                if (! is_array($bandConfig) || ! isset($bandConfig['elements']) || ! is_array($bandConfig['elements'])) {
                    $errors["bands.{$bandName}.elements"] = "Band {$bandName} must contain an elements array.";

                    continue;
                }

                foreach ($bandConfig['elements'] as $index => $element) {
                    $prefix = "bands.{$bandName}.elements.{$index}";
                    $this->validateElement($element, $prefix, $errors);
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $layout;
    }

    /**
     * Validate an individual layout element.
     */
    private function validateElement(mixed $element, string $prefix, array &$errors): void
    {
        if (! is_array($element)) {
            $errors[$prefix] = 'Element must be an object.';

            return;
        }

        $type = $element['type'] ?? null;
        if (! $type || ! in_array($type, self::ALLOWED_ELEMENT_TYPES, true)) {
            $errors["{$prefix}.type"] = "Unsupported element type [{$type}]. Allowed: ".implode(', ', self::ALLOWED_ELEMENT_TYPES);

            return;
        }

        // Geometry checks
        $x = $element['x_mm'] ?? null;
        $y = $element['y_mm'] ?? null;
        $width = $element['width_mm'] ?? null;
        $height = $element['height_mm'] ?? null;

        if ($x === null || ! is_numeric($x) || $x < 0) {
            $errors["{$prefix}.x_mm"] = 'Element x_mm must be a non-negative number.';
        }
        if ($y === null || ! is_numeric($y) || $y < 0) {
            $errors["{$prefix}.y_mm"] = 'Element y_mm must be a non-negative number.';
        }
        if ($width === null || ! is_numeric($width) || $width <= 0) {
            $errors["{$prefix}.width_mm"] = 'Element width_mm must be a positive number.';
        }
        if ($height === null || ! is_numeric($height) || $height <= 0) {
            $errors["{$prefix}.height_mm"] = 'Element height_mm must be a positive number.';
        }

        // Type-specific checks
        if ($type === 'static_text') {
            if (! isset($element['text']) || ! is_string($element['text'])) {
                $errors["{$prefix}.text"] = 'Static text element requires a string text property.';
            }
        } elseif ($type === 'bound_text') {
            if (empty($element['field']) || ! is_string($element['field'])) {
                $errors["{$prefix}.field"] = 'Bound text element requires a field binding path.';
            }
        } elseif ($type === 'table') {
            if (empty($element['columns']) || ! is_array($element['columns'])) {
                $errors["{$prefix}.columns"] = 'Table element requires a columns array.';
            }
        } elseif ($type === 'image') {
            if (! isset($element['asset_id']) || filter_var($element['asset_id'], FILTER_VALIDATE_INT) === false || (int) $element['asset_id'] < 1) {
                $errors["{$prefix}.asset_id"] = 'Image element requires a positive organization branding asset_id.';
            }
            if (array_key_exists('source', $element)) {
                $errors["{$prefix}.source"] = 'Image sources are not accepted. Upload a private branding asset and bind it with asset_id.';
            }
        }

        // Security check: no script injection in any text/value
        $json = json_encode($element);
        if (preg_match('/<script|javascript:|onerror=|onload=/i', (string) $json)) {
            $errors["{$prefix}.security"] = 'Element contains forbidden script tokens.';
        }
    }
}
