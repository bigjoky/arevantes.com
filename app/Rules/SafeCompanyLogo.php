<?php

namespace App\Rules;

use Closure;
use DOMDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SafeCompanyLogo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Selecciona un archivo de imagen.');

            return;
        }
        if (strtolower($value->getClientOriginalExtension()) !== 'svg') {
            $info = @getimagesize($value->getRealPath());
            if (! $info || ! in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
                $fail('Formato de imagen no válido.');
            }

            return;
        }
        $xml = file_get_contents($value->getRealPath());
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument;
            if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) || ! $doc->loadXML($xml, LIBXML_NONET) || $doc->documentElement?->localName !== 'svg') {
                $fail('SVG no válido o inseguro.');

                return;
            }
            foreach ($doc->getElementsByTagName('*') as $element) {
                if (! in_array($element->localName, ['svg', 'g', 'path', 'polygon', 'polyline', 'rect', 'circle', 'ellipse', 'line', 'title', 'desc', 'defs', 'linearGradient', 'radialGradient', 'stop', 'clipPath'], true) || $element->namespaceURI !== 'http://www.w3.org/2000/svg') {
                    $fail('El SVG contiene elementos no permitidos.');

                    return;
                }
                foreach ($element->attributes as $attr) {
                    if (! in_array($attr->name, ['id', 'version', 'width', 'height', 'viewBox', 'preserveAspectRatio', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'transform', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'opacity', 'offset', 'stop-color', 'stop-opacity', 'gradientUnits', 'gradientTransform', 'clip-path', 'clip-rule'], true) || preg_match('/[&<>\\\\]|(?:javascript|data|https?):|url\s*\((?!\s*#[a-zA-Z0-9_-]+\s*\))/i', $attr->value)) {
                        $fail('El SVG contiene atributos o referencias no permitidos.');

                        return;
                    }
                }
            }
            foreach ((new \DOMXPath($doc))->query('//processing-instruction()') as $node) {
                if ($node->nodeType === XML_PI_NODE) {
                    $fail('El SVG contiene instrucciones no permitidas.');

                    return;
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
