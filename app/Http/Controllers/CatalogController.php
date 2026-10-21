<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\ReadingProgress;
use App\Models\Work;
use App\Services\SearchCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    public function home(SearchCatalog $search)
    {
        return view('home', ['featured' => $search->query([])->where('featured', true)->limit(6)->get(), 'latest' => $search->query([])->limit(6)->get(), 'categories' => Category::withCount(['works' => fn ($q) => $q->publiclyVisible()])->get()]);
    }

    public function library(Request $request, SearchCatalog $search)
    {
        $filters = $this->filters($request);

        return view('library', ['works' => $search->query($filters)->paginate(18)->withQueryString(), 'categories' => Category::all(), 'filters' => $filters]);
    }

    private function filters(Request $request): array
    {
        return $request->validate(['q' => 'nullable|string|max:120', 'language' => 'nullable|in:ar,fr,en', 'category' => 'nullable|string|max:80', 'kind' => 'nullable|in:novel,book,short_story', 'completion' => 'nullable|in:ongoing,completed', 'sort' => 'nullable|in:newest,rating,popular']);
    }

    public function search(Request $request, SearchCatalog $search)
    {
        $filters = $this->filters($request);
        if (mb_strlen(trim($filters['q'] ?? '')) < 2) {
            return response()->json(['results' => []]);
        }

        return response()->json(['results' => $search->query($filters)->limit(8)->get()->map(fn ($w) => ['title' => $w->title, 'author' => $w->author->name, 'cover' => $w->coverUrl(), 'url' => route('works.show', $w), 'language' => $w->language])]);
    }

    public function show(Request $request, Work $work)
    {
        Gate::authorize('view', $work);
        $work->load(['author', 'category'])->loadAvg('reviews', 'rating')->loadCount('views');

        return view('works.show', ['work' => $work, 'chapters' => $work->chapters()->published()->get(), 'comments' => $work->comments()->whereNull('parent_id')->where('status', 'visible')->with(['user', 'replies'])->latest()->paginate(15), 'reviews' => $work->reviews()->with('user')->latest()->limit(10)->get(), 'files' => $work->files()->where('scan_status', 'clean')->get()]);
    }

    public function cover(Work $work)
    {
        Gate::authorize('view', $work);
        abort_unless($work->cover_path && Storage::disk('local')->exists($work->cover_path), 404);

        return Storage::disk('local')->response($work->cover_path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => $work->isPublic() ? 'public, max-age=3600' : 'private, no-store']);
    }

    public function read(Request $request, Work $work, ?int $chapter = null)
    {
        Gate::authorize('view', $work);
        $preview = $request->user() && ($request->user()->id === $work->owner_id || $request->user()->isStaff());
        $chapters = $work->chapters()->when(! $preview, fn ($q) => $q->published())->get();
        abort_if($chapters->isEmpty(), 404);
        $progress = $request->user() ? ReadingProgress::where('user_id', $request->user()->id)->where('work_id', $work->id)->first() : null;
        $current = $chapter ? $chapters->firstWhere('id', $chapter) : ($chapters->firstWhere('id', $progress?->chapter_id) ?? $chapters->first());
        abort_unless($current, 404);
        if ($work->isPublic()) {
            DB::table('work_views')->insertOrIgnore(['work_id' => $work->id, 'visitor_hash' => hash_hmac('sha256', ($request->user()?->id ?? $request->session()->getId()).'|'.today()->toDateString(), config('app.key')), 'day' => today()->toDateString()]);
        }
        $index = $chapters->search(fn ($c) => $c->id === $current->id);

        return view('reader', ['work' => $work, 'chapter' => $current, 'chapters' => $chapters, 'previous' => $chapters->get($index - 1), 'next' => $chapters->get($index + 1), 'offset' => $progress?->chapter_id === $current->id ? $progress->offset : 0, 'readingMode' => $request->query('mode', $progress?->mode ?? 'scroll')]);
    }

    public function author(Author $author)
    {
        return view('author', ['author' => $author, 'works' => $author->works()->publiclyVisible()->with(['author', 'category'])->withAvg('reviews', 'rating')->paginate(18), 'followers' => $author->followers()->count()]);
    }

    public function categories()
    {
        return view('categories', ['categories' => Category::withCount(['works' => fn ($q) => $q->publiclyVisible()])->get()]);
    }

    public function blog()
    {
        return view('blog', ['posts' => Post::where('status', 'published')->where('published_at', '<=', now())->latest('published_at')->paginate(12)]);
    }

    public function post(Post $post)
    {
        abort_unless($post->status === 'published' && $post->published_at?->lte(now()), 404);

        return view('post', compact('post'));
    }
}
