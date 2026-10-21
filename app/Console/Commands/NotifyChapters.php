<?php

namespace App\Console\Commands;

use App\Models\Chapter;
use App\Notifications\NewChapter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyChapters extends Command
{
    protected $signature = 'imary:notify-chapters';

    protected $description = 'Notify followers about newly available chapters';

    public function handle(): int
    {
        Chapter::published()->whereNull('notified_at')->whereHas('work', fn ($q) => $q->publiclyVisible())->chunkById(100, function ($chapters) {
            foreach ($chapters as $chapter) {
                DB::transaction(function () use ($chapter) {
                    $chapter = Chapter::whereKey($chapter->id)->lockForUpdate()->first();
                    if (! $chapter || $chapter->notified_at) {
                        return;
                    }$chapter->work->author->followers()->whereNull('banned_at')->chunkById(200, function ($users) use ($chapter) {
                        foreach ($users as $user) {
                            $user->notify(new NewChapter($chapter));
                        }
                    }, 'users.id', 'id');
                    $chapter->update(['notified_at' => now()]);
                });
            }
        });

        return self::SUCCESS;
    }
}
