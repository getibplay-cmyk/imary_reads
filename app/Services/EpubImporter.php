<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class EpubImporter
{
    private function fail(): never
    {
        throw ValidationException::withMessages(['file' => __('ui.invalid_epub')]);
    }

    public function validate(string $path): void
    {
        $this->read($path, false);
    }

    private function xml(string $data): DOMDocument
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $data)) {
            $this->fail();
        }
        $dom = new DOMDocument;
        $old = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($data, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        if (! $ok) {
            $this->fail();
        }

        return $dom;
    }

    public function read(string $path, bool $extract = true): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail();
        }
        try {
            if ($zip->numFiles > 1000 || $zip->getFromName('mimetype') !== 'application/epub+zip') {
                $this->fail();
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $s = $zip->statIndex($i);
                $total += $s['size'];
                if ($total > config('imary.max_import_bytes') || preg_match('~(^/|(^|/)\.\.(/|$)|\\\\|\x00)~', $s['name']) || $s['size'] > max(1, $s['comp_size']) * 200) {
                    $this->fail();
                }
            }
            $container = $zip->getFromName('META-INF/container.xml');
            if (! $container) {
                $this->fail();
            }
            $xp = new DOMXPath($this->xml($container));
            $node = $xp->query('//*[local-name()="rootfile"]')->item(0);
            if (! $node) {
                $this->fail();
            }
            $opf = $node->getAttribute('full-path');
            $data = $zip->getFromName($opf);
            if (! $data) {
                $this->fail();
            }
            $xp = new DOMXPath($this->xml($data));
            $manifest = [];
            foreach ($xp->query('//*[local-name()="manifest"]/*[local-name()="item"]') as $n) {
                $manifest[$n->getAttribute('id')] = [$n->getAttribute('href'), $n->getAttribute('media-type')];
            }
            $result = [];
            $base = dirname($opf);
            $base = $base === '.' ? '' : $base.'/';
            foreach ($xp->query('//*[local-name()="spine"]/*[local-name()="itemref"]') as $n) {
                [$href,$mime] = $manifest[$n->getAttribute('idref')] ?? ['', ''];
                $href = rawurldecode(explode('#', $href)[0]);
                if (! $href || ! in_array($mime, ['application/xhtml+xml', 'text/html'], true) || preg_match('~(^/|(^|/)\.\.(/|$)|:|\\\\)~', $href)) {
                    $this->fail();
                }
                $html = $zip->getFromName($base.$href);
                if ($html === false) {
                    $this->fail();
                }
                if (preg_match('/<!ENTITY/i', $html)) {
                    $this->fail();
                }
                if ($extract) {
                    if ($mime === 'application/xhtml+xml') {
                        $dom = $this->xml($html);
                        $body = (new DOMXPath($dom))->query('//*[local-name()="body"]')->item(0);
                        if (! $body) {
                            $this->fail();
                        }$html = '';
                        foreach ($body->childNodes as $child) {
                            $html .= $dom->saveXML($child);
                        }
                    } elseif (preg_match('~<body\b[^>]*>([\s\S]*?)</body\s*>~i', $html, $body)) {
                        $html = $body[1];
                    }$clean = app(HtmlContent::class)->clean($html);
                    $plain = app(HtmlContent::class)->plain($clean);
                    if ($plain !== '') {
                        $result[] = ['title' => __('ui.chapter').' '.(count($result) + 1), 'content' => $clean];
                    }
                }
            }
            if ($extract && ! count($result)) {
                $this->fail();
            }

            return $result;
        } finally {
            $zip->close();
        }
    }
}
