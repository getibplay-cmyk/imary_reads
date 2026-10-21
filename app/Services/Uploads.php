<?php

namespace App\Services;

use App\Models\Work;
use App\Models\WorkFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Uploads
{
    public function cover(UploadedFile $file): string
    {
        $bytes = file_get_contents($file->getRealPath());
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[0] * $size[1] > 16000000 || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['cover' => __('ui.invalid_image')]);
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw ValidationException::withMessages(['cover' => __('ui.invalid_image')]);
        }
        $width = min(1200, imagesx($image));
        $height = (int) round(imagesy($image) * $width / imagesx($image));
        $target = imagecreatetruecolor($width, $height);
        imagefill($target, 0, 0, imagecolorallocate($target, 10, 19, 39));
        imagecopyresampled($target, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        ob_start();
        imagejpeg($target, null, 88);
        $output = ob_get_clean();
        imagedestroy($image);
        imagedestroy($target);
        $path = 'covers/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $output);

        return $path;
    }

    public function document(Work $work, UploadedFile $file, bool $import): WorkFile
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $allowed = ['pdf' => ['application/pdf'], 'epub' => ['application/epub+zip', 'application/zip'], 'txt' => ['text/plain']];
        if (! isset($allowed[$ext]) || ! in_array($mime, $allowed[$ext], true)) {
            throw ValidationException::withMessages(['file' => __('ui.invalid_file')]);
        }
        if ($import && $file->getSize() > config('imary.max_import_bytes')) {
            throw ValidationException::withMessages(['file' => __('ui.import_too_large')]);
        }
        if ($import && $ext === 'txt' && ! mb_check_encoding(file_get_contents($file->getRealPath()), 'UTF-8')) {
            throw ValidationException::withMessages(['file' => __('ui.text_encoding')]);
        }
        if ($ext === 'epub') {
            app(EpubImporter::class)->validate($file->getRealPath());
        }
        if ($import && $ext === 'pdf') {
            throw ValidationException::withMessages(['file' => __('ui.pdf_import_notice')]);
        }
        $path = $file->store('quarantine', 'local');

        return $work->files()->create(['path' => $path, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'mime' => $ext === 'epub' ? 'application/epub+zip' : $mime, 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'import_requested' => $import]);
    }
}
