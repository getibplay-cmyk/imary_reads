<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlContent
{
    public function clean(string $html): string
    {
        abort_if(strlen($html) > config('imary.max_import_bytes'), 422, __('ui.import_too_large'));
        $config = (new HtmlSanitizerConfig)->withMaxInputLength(config('imary.max_import_bytes'));
        foreach (['div', 'span', 'section', 'article', 'a', 'h1', 'h4', 'h5', 'h6', 'figure', 'figcaption'] as $tag) {
            $config = $config->blockElement($tag);
        }
        foreach (['p', 'br', 'strong', 'em', 'u', 's', 'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'hr'] as $tag) {
            $config = $config->allowElement($tag, []);
        }

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    public function plain(string $html): string
    {
        return trim(html_entity_decode(strip_tags(preg_replace('~</(?:p|h[23]|li|blockquote)>|<br\s*/?>~i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
