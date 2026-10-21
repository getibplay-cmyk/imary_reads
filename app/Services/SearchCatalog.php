<?php

namespace App\Services;

use App\Models\Work;

class SearchCatalog
{
    public function query(array $filters)
    {
        $q = Work::publiclyVisible()->with(['author', 'category'])->withAvg('reviews', 'rating')->withCount(['chapters' => fn ($q) => $q->published(), 'views']);
        if ($term = trim($filters['q'] ?? '')) {
            // Bind values; escape wildcard characters so input is a literal term.
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $q->where(fn ($q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term])->orWhereHas('author', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])));
        }
        foreach (['language', 'completion', 'kind'] as $field) {
            if (! empty($filters[$field])) {
                $q->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['category'])) {
            $q->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        return match ($filters['sort'] ?? 'newest') {
            'rating' => $q->orderByDesc('reviews_avg_rating'),'popular' => $q->orderByDesc('views_count'),default => $q->orderByDesc('published_at')
        };
    }
}
