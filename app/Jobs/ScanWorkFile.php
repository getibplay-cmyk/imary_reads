<?php

namespace App\Jobs;

use App\Models\Work;
use App\Models\WorkFile;
use App\Services\ChapterWriter;
use App\Services\EpubImporter;
use App\Services\HtmlContent;
use App\Services\VirusScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScanWorkFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public int $fileId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(VirusScanner $scanner): void
    {
        $file = WorkFile::find($this->fileId);
        if (! $file || $file->scan_status !== 'pending') {
            return;
        }
        $path = Storage::disk('local')->path($file->path);
        if (! $scanner->clean($path)) {
            $file->update(['scan_status' => 'rejected', 'scan_note' => 'Malware detected']);
            Storage::disk('local')->delete($file->path);

            return;
        }
        if (! hash_equals($file->sha256, hash_file('sha256', $path))) {
            throw new \RuntimeException('File integrity mismatch');
        }
        DB::transaction(function () use ($file, $path) {
            $file = WorkFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
            if ($file->scan_status !== 'pending') {
                return;
            }
            $work = Work::whereKey($file->work_id)->lockForUpdate()->firstOrFail();
            if ($file->import_requested && ! $file->imported_at) {
                $chapters = $file->mime === 'application/epub+zip' ? app(EpubImporter::class)->read($path) : [['title' => pathinfo($file->original_name, PATHINFO_FILENAME), 'content' => '<p>'.nl2br(e(mb_convert_encoding(file_get_contents($path), 'UTF-8', 'UTF-8'))).'</p>']];
                foreach ($chapters as $chapter) {
                    $plain = app(HtmlContent::class)->plain(app(HtmlContent::class)->clean($chapter['content']));
                    if (mb_strlen($chapter['content']) <= 300000) {
                        app(ChapterWriter::class)->save($work, $chapter + ['status' => 'draft']);

                        continue;
                    }foreach (mb_str_split($plain, 50000) as $part => $text) {
                        app(ChapterWriter::class)->save($work, ['title' => mb_substr($chapter['title'], 0, 150).' · '.($part + 1), 'content' => '<p>'.nl2br(e($text)).'</p>', 'status' => 'draft']);
                    }
                }
                $file->imported_at = now();
            }
            $file->scan_status = 'clean';
            $file->scan_note = null;
            $file->save();
        });
    }

    public function failed(?\Throwable $e): void
    {
        WorkFile::whereKey($this->fileId)->where('scan_status', 'pending')->update(['scan_note' => 'Scan or import did not complete. Retry after checking the scanner and file.']);
    }
}
