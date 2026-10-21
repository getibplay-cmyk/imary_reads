<?php

namespace App\Notifications;

use App\Models\Chapter;
use Illuminate\Notifications\Notification;

class NewChapter extends Notification
{
    public function __construct(public Chapter $chapter) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->chapter->title, 'work' => $this->chapter->work->title, 'url' => route('reader', [$this->chapter->work, $this->chapter->id])];
    }
}
