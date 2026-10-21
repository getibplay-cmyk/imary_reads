<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Work extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'published_at' => 'datetime', 'rights_confirmed' => 'boolean', 'allow_download' => 'boolean', 'allow_comments' => 'boolean', 'featured' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class)->orderBy('position');
    }

    public function files()
    {
        return $this->hasMany(WorkFile::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }

    public function views()
    {
        return $this->hasMany(WorkView::class);
    }

    public function scopePubliclyVisible(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->where(fn ($q) => $q->whereNull('owner_id')->orWhereHas('owner', fn ($q) => $q->whereNull('banned_at')));
    }

    public function isPublic(): bool
    {
        return $this->status === 'published' && $this->published_at?->lte(now()) && ! $this->trashed() && ! $this->owner?->banned_at;
    }

    public function coverUrl(): string
    {
        return $this->cover_path ? route('works.cover', $this) : asset('images/'.($this->cover_asset ?: 'hero.webp'));
    }
}
