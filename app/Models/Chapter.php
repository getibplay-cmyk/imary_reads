<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chapter extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    public function work()
    {
        return $this->belongsTo(Work::class);
    }

    public function versions()
    {
        return $this->hasMany(ChapterVersion::class)->orderByDesc('revision');
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published')->where('published_at', '<=', now());
    }
}
