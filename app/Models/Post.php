<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['title' => 'array', 'body' => 'array', 'published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function text(string $key): string
    {
        return $this->$key[app()->getLocale()] ?? $this->$key['ar'] ?? '';
    }
}
