<?php

namespace App\Services;

use App\Models\Work;
use ZipArchive;

class BookExport
{
    public function txt(Work $work): string
    {
        return $work->title."\n".$work->author->name."\n\n".$work->chapters()->published()->get()->map(fn ($c) => $c->title."\n\n".$c->plain_text)->implode("\n\n────────\n\n");
    }

    public function epub(Work $work): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imary-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $manifest = '';
        $spine = '';
        $nav = '';
        $dir = $work->language === 'ar' ? 'rtl' : 'ltr';
        $esc = fn ($s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        foreach ($work->chapters()->published()->get() as $i => $c) {
            $id = 'ch'.$i;
            $file = $id.'.xhtml';
            $manifest .= '<item id="'.$id.'" href="'.$file.'" media-type="application/xhtml+xml"/>';
            $spine .= '<itemref idref="'.$id.'"/>';
            $nav .= '<li><a href="'.$file.'">'.$esc($c->title).'</a></li>';
            $paragraphs = '';
            foreach (explode("\n", $c->plain_text) as $p) {
                if (trim($p) !== '') {
                    $paragraphs .= '<p>'.$esc($p).'</p>';
                }
            }
            $zip->addFromString('OEBPS/'.$file, '<?xml version="1.0" encoding="UTF-8"?><html xmlns="http://www.w3.org/1999/xhtml" xml:lang="'.$esc($work->language).'" dir="'.$dir.'"><head><title>'.$esc($c->title).'</title><link rel="stylesheet" href="style.css"/></head><body><h1>'.$esc($c->title).'</h1>'.$paragraphs.'</body></html>');
        }
        $zip->addFromString('OEBPS/style.css', 'body{font-family:serif;line-height:1.9;direction:'.$dir.'}h1{text-align:center}p{text-align:justify}');
        $zip->addFromString('OEBPS/nav.xhtml', '<?xml version="1.0" encoding="UTF-8"?><html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="'.$esc($work->language).'"><head><title>'.$esc($work->title).'</title></head><body><nav epub:type="toc"><ol>'.$nav.'</ol></nav></body></html>');
        $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0" encoding="UTF-8"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="uid"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:identifier id="uid">urn:imary:'.$work->id.'</dc:identifier><dc:title>'.$esc($work->title).'</dc:title><dc:creator>'.$esc($work->author->name).'</dc:creator><dc:language>'.$esc($work->language).'</dc:language><dc:rights>'.$esc($work->license).'</dc:rights><meta property="dcterms:modified">'.gmdate('Y-m-d\TH:i:s\Z').'</meta></metadata><manifest><item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/><item id="css" href="style.css" media-type="text/css"/>'.$manifest.'</manifest><spine page-progression-direction="'.$dir.'">'.$spine.'</spine></package>');
        $zip->close();

        return $path;
    }
}
