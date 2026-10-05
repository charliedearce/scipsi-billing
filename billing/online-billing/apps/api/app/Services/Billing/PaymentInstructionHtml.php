<?php

namespace App\Services\Billing;

use App\Models\PaymentGroup;
use App\Models\PaymentPolicyImage;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Stores customer-facing payment instructions as plain text or a small HTML subset.
 * Bank images are private files referenced by a stable token, never as inline data.
 */
class PaymentInstructionHtml
{
    private const MAX_INPUT_BYTES = 8000000;

    private const MAX_STORED_HTML = 20000;

    private const MAX_IMAGES = 5;

    private const MAX_IMAGE_BYTES = 2000000;

    /** @var array<string, true> */
    private const ALLOWED = [
        'p' => true, 'br' => true, 'strong' => true, 'b' => true, 'em' => true, 'i' => true,
        'u' => true, 's' => true, 'ul' => true, 'ol' => true, 'li' => true, 'a' => true,
        'img' => true, 'h1' => true, 'h2' => true, 'h3' => true, 'blockquote' => true, 'span' => true,
    ];

    /** @var array<string, true> */
    private const DROP_WITH_CHILDREN = [
        'script' => true, 'style' => true, 'iframe' => true, 'object' => true, 'embed' => true,
        'svg' => true, 'math' => true, 'link' => true, 'meta' => true,
    ];

    public function store(User $actor, string $input): string
    {
        $input = trim($input);
        if ($input === '' || strlen($input) > self::MAX_INPUT_BYTES) {
            throw ValidationException::withMessages([
                'manual_instructions' => ['Write the customer payment instructions, including any bank image, within the size limit.'],
            ]);
        }
        if (! preg_match('/<\/?[a-z][^>]*>/i', $input)) {
            $visible = trim(preg_replace('/\s+/u', ' ', $input) ?? '');
            if (mb_strlen($visible) < 10) {
                throw ValidationException::withMessages([
                    'manual_instructions' => ['Write at least 10 characters of payment instructions.'],
                ]);
            }

            return $input;
        }

        $previous = libxml_use_internal_errors(true);
        $source = new DOMDocument('1.0', 'UTF-8');
        $source->loadHTML('<?xml encoding="utf-8"><div id="payment-instruction-root">'.$input.'</div>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = (new \DOMXPath($source))->query('//*[@id="payment-instruction-root"]')->item(0);
        if (! $root instanceof DOMElement) {
            throw ValidationException::withMessages([
                'manual_instructions' => ['The payment instructions could not be read. Use the editor text and image tools.'],
            ]);
        }

        $clean = new DOMDocument('1.0', 'UTF-8');
        $container = $clean->createElement('div');
        $clean->appendChild($container);
        $imageCount = 0;
        $this->copyChildren($root, $clean, $container, $actor, $imageCount);

        $html = '';
        foreach ($container->childNodes as $child) {
            $html .= $clean->saveHTML($child);
        }
        $html = trim($html);
        $visible = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (mb_strlen($visible) < 10 || mb_strlen($html) > self::MAX_STORED_HTML) {
            throw ValidationException::withMessages([
                'manual_instructions' => ['Write at least 10 characters of payment instructions. Keep the formatted text within the instruction limit; images are stored separately.'],
            ]);
        }

        return $html;
    }

    public function canView(User $actor, PaymentPolicyImage $image): bool
    {
        if ($image->organization_id !== $actor->organization_id) {
            return false;
        }
        if ($actor->hasPermission('payment_policies:view')) {
            return true;
        }

        $needle = 'payment-policy-image:'.$image->id;

        return PaymentGroup::query()
            ->where('organization_id', $actor->organization_id)
            ->where('created_by_user_id', $actor->id)
            ->where('manual_instructions_snapshot', 'like', '%'.$needle.'%')
            ->pluck('manual_instructions_snapshot')
            ->contains(fn ($html): bool => preg_match('/'.preg_quote($needle, '/').'(?!\d)/', (string) $html) === 1);
    }

    private function copyChildren(DOMNode $source, DOMDocument $clean, DOMNode $parent, User $actor, int &$imageCount): void
    {
        foreach ($source->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $parent->appendChild($clean->createTextNode((string) $child->nodeValue));

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (isset(self::DROP_WITH_CHILDREN[$tag])) {
                continue;
            }
            if (! isset(self::ALLOWED[$tag])) {
                $this->copyChildren($child, $clean, $parent, $actor, $imageCount);

                continue;
            }
            if ($tag === 'img') {
                $parent->appendChild($this->imageElement($clean, $child, $actor, $imageCount));

                continue;
            }
            $element = $clean->createElement($tag);
            if ($tag === 'a') {
                $href = $this->safeHref($child->getAttribute('href'));
                if ($href === null) {
                    $this->copyChildren($child, $clean, $parent, $actor, $imageCount);

                    continue;
                }
                $element->setAttribute('href', $href);
                $element->setAttribute('rel', 'noopener noreferrer');
                $element->setAttribute('target', '_blank');
            }
            $parent->appendChild($element);
            if ($tag !== 'br') {
                $this->copyChildren($child, $clean, $element, $actor, $imageCount);
            }
        }
    }

    private function imageElement(DOMDocument $clean, DOMElement $source, User $actor, int &$imageCount): DOMElement
    {
        $imageCount++;
        if ($imageCount > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'manual_instructions' => ['A payment instruction can include up to 5 images.'],
            ]);
        }
        $image = $this->resolveImage($actor, trim($source->getAttribute('src')));
        $alt = trim($source->getAttribute('alt'));
        $element = $clean->createElement('img');
        $element->setAttribute('src', 'payment-policy-image:'.$image->id);
        $element->setAttribute('alt', $alt !== '' ? mb_substr($alt, 0, 200) : 'Bank instruction');

        return $element;
    }

    private function resolveImage(User $actor, string $src): PaymentPolicyImage
    {
        if (preg_match('/^payment-policy-image:(\d+)$/', $src, $matches) === 1) {
            $image = PaymentPolicyImage::query()
                ->where('organization_id', $actor->organization_id)
                ->find((int) $matches[1]);
            if (! $image) {
                throw ValidationException::withMessages([
                    'manual_instructions' => ['One bank image is no longer available. Insert it again.'],
                ]);
            }

            return $image;
        }

        if (preg_match('#^data:image/(png|jpeg|jpg|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $src, $matches) !== 1) {
            throw ValidationException::withMessages([
                'manual_instructions' => ['Insert a PNG, JPEG, or WebP bank image from this editor.'],
            ]);
        }
        if (strlen($matches[2]) > (self::MAX_IMAGE_BYTES * 2)) {
            throw ValidationException::withMessages([
                'manual_instructions' => ['Each bank image must be 2 MB or smaller.'],
            ]);
        }
        $bytes = base64_decode($matches[2], true);
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'manual_instructions' => ['Each bank image must be a valid PNG, JPEG, or WebP file of 2 MB or smaller.'],
            ]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
        if ($extension === null) {
            throw ValidationException::withMessages([
                'manual_instructions' => ['Each bank image must be a PNG, JPEG, or WebP file.'],
            ]);
        }
        $sha = hash('sha256', $bytes);
        $existing = PaymentPolicyImage::query()
            ->where('organization_id', $actor->organization_id)
            ->where('sha256', $sha)
            ->first();
        if ($existing) {
            return $existing;
        }

        $path = 'payment-policy-images/'.$actor->organization_id.'/'.$sha.'.'.$extension;
        Storage::disk('local_private')->put($path, $bytes);

        return PaymentPolicyImage::create([
            'organization_id' => $actor->organization_id,
            'uploaded_by_user_id' => $actor->id,
            'storage_path' => $path,
            'mime_type' => $mime,
            'byte_size' => strlen($bytes),
            'sha256' => $sha,
        ]);
    }

    private function safeHref(string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || preg_match('/[\s\x00-\x1f]/', $href) === 1) {
            return null;
        }
        if (preg_match('#^https?://#i', $href) === 1 || preg_match('#^mailto:#i', $href) === 1) {
            return $href;
        }

        return null;
    }
}
