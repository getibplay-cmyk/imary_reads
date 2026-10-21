<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Work;
use Illuminate\Support\Facades\DB;

class ChapterWriter
{
    public function save(Work $work, array $data, ?Chapter $chapter = null): Chapter
    {
        return DB::transaction(function () use ($work, $data, $chapter) {
            // Lock the parent as well: position allocation and revisions remain unique.
            Work::whereKey($work->id)->lockForUpdate()->firstOrFail();
            $html = app(HtmlContent::class)->clean($data['content']);
            $plain = app(HtmlContent::class)->plain($html);
            abort_if(mb_strlen(trim($plain)) < 1, 422, __('ui.content_required'));
            if ($chapter) {
                $chapter = Chapter::whereKey($chapter->id)->lockForUpdate()->firstOrFail();
                abort_if((int) $data['revision'] !== $chapter->revision, 409, __('ui.conflict'));
            } else {
                $chapter = new Chapter(['work_id' => $work->id, 'position' => ($work->chapters()->max('position') ?? 0) + 1, 'revision' => 0]);
            }
            $chapter->fill(['title' => $data['title'], 'content' => $html, 'plain_text' => $plain, 'word_count' => count(preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY)), 'status' => $data['status'], 'published_at' => $data['status'] === 'published' ? ($data['published_at'] ?? $chapter->published_at ?? now()) : null, 'revision' => $chapter->revision + 1]);
            $chapter->save();
            $chapter->versions()->create(['user_id' => auth()->id(), 'revision' => $chapter->revision, 'title' => $chapter->title, 'content' => $html, 'created_at' => now()]);
            Audit::record('chapter.saved', $chapter, ['revision' => $chapter->revision]);

            return $chapter;
        });
    }
}
