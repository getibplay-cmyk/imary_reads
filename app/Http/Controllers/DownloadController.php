<?php

namespace App\Http\Controllers;

use App\Models\DownloadEvent;
use App\Models\Work;
use App\Models\WorkFile;
use App\Services\BookExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function export(Request $request, Work $work, string $format, BookExport $export)
    {
        Gate::authorize('download', $work);
        abort_unless(in_array($format, ['txt', 'epub'], true), 404);
        abort_unless($work->chapters()->published()->exists(), 404);
        DownloadEvent::create(['work_id' => $work->id, 'user_id' => $request->user()->id, 'format' => $format, 'created_at' => now()]);
        if ($format === 'txt') {
            return response($export->txt($work), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$work->slug.'.txt"', 'Cache-Control' => 'no-store']);
        }

        return response()->download($export->epub($work), $work->slug.'.epub', ['Content-Type' => 'application/epub+zip', 'Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }

    public function file(Request $request, Work $work, WorkFile $file)
    {
        Gate::authorize('download', $work);
        abort_unless($file->work_id === $work->id && $file->scan_status === 'clean' && Storage::disk('local')->exists($file->path), 404);
        DownloadEvent::create(['work_id' => $work->id, 'user_id' => $request->user()->id, 'format' => pathinfo($file->original_name, PATHINFO_EXTENSION), 'created_at' => now()]);

        return Storage::disk('local')->download($file->path, $file->original_name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'"]);
    }
}
