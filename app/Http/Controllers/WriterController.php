<?php

namespace App\Http\Controllers;

use App\Jobs\ScanWorkFile;
use App\Models\Author;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Comment;
use App\Models\Work;
use App\Models\WorkFile;
use App\Services\Audit;
use App\Services\ChapterWriter;
use App\Services\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WriterController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('create', Work::class);
        $works = $request->user()->works()->when($request->query('status') === 'archived', fn ($q) => $q->onlyTrashed())->with(['author', 'category'])->withCount(['chapters', 'views', 'favorites'])->withAvg('reviews', 'rating')->latest()->paginate(15)->withQueryString();

        return view('writer.index', compact('works'));
    }

    public function create()
    {
        Gate::authorize('create', Work::class);

        return view('writer.work', ['work' => new Work, 'categories' => Category::all(), 'authors' => auth()->user()->role === 'admin' ? Author::all() : collect()]);
    }

    public function edit(Work $work)
    {
        Gate::authorize('update', $work);

        return view('writer.work', ['work' => $work, 'categories' => Category::all(), 'authors' => auth()->user()->role === 'admin' ? Author::all() : collect()]);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => 'required|string|max:180', 'synopsis' => 'required|string|min:20|max:6000', 'category_id' => 'required|exists:categories,id', 'language' => 'required|in:ar,fr,en', 'kind' => 'required|in:novel,book,short_story', 'completion' => 'required|in:ongoing,completed', 'license' => ['required', Rule::in(config('imary.licenses'))], 'rights_note' => 'nullable|string|max:3000', 'source_url' => 'nullable|url:http,https|max:1000', 'rights_confirmed' => 'nullable|boolean', 'allow_download' => 'nullable|boolean', 'allow_comments' => 'nullable|boolean', 'cover' => 'nullable|file|mimes:jpeg,png,webp|max:4096', 'tags' => 'nullable|string|max:300', 'submit' => 'nullable|boolean', 'author_id' => 'nullable|exists:authors,id']);
    }

    private function data(Request $request, array $validated): array
    {
        $data = collect($validated)->except(['cover', 'submit', 'author_id'])->all();
        foreach (['rights_confirmed', 'allow_download', 'allow_comments'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['tags'] = array_slice(array_values(array_filter(array_map('trim', explode(',', $validated['tags'] ?? '')))), 0, 10);

        return $data;
    }

    public function store(Request $request, Uploads $uploads)
    {
        Gate::authorize('create', Work::class);
        $validated = $this->validated($request);
        $data = $this->data($request, $validated);
        $author = $request->user()->author;
        if ($request->user()->role === 'admin' && $request->filled('author_id')) {
            $author = Author::findOrFail($validated['author_id']);
            $request->validate(['rights_note' => 'required|min:20']);
        }
        if (! $author) {
            $author = Author::create(['user_id' => $request->user()->id, 'name' => $request->user()->name, 'slug' => 'author-'.Str::ulid()]);
        }
        if ($request->boolean('submit')) {
            $request->validate(['rights_confirmed' => 'accepted']);
        }
        $data += ['owner_id' => $request->user()->id, 'author_id' => $author->id, 'slug' => Str::slug($validated['title']).'-'.Str::lower(Str::random(8)), 'status' => $request->boolean('submit') ? 'pending' : 'draft'];
        if ($request->hasFile('cover')) {
            $data['cover_path'] = $uploads->cover($request->file('cover'));
        }
        $work = Work::create($data);
        Audit::record('work.created', $work);

        return redirect()->route('writer.chapters', $work)->with('status', __('ui.saved'));
    }

    public function update(Request $request, Work $work, Uploads $uploads)
    {
        Gate::authorize('update', $work);
        $validated = $this->validated($request);
        $data = $this->data($request, $validated);
        if ($request->boolean('submit')) {
            $request->validate(['rights_confirmed' => 'accepted']);
            $data['status'] = 'pending';
        } elseif ($work->status === 'published' && ! $request->user()->isStaff()) {
            $data['status'] = 'pending';
        }
        if ($request->hasFile('cover')) {
            $data['cover_path'] = $uploads->cover($request->file('cover'));
        }
        $oldCover = $work->cover_path;
        DB::transaction(function () use ($work, $data) {
            $work->update($data);
            Audit::record('work.updated', $work);
        });
        if (isset($data['cover_path']) && $oldCover) {
            Storage::disk('local')->delete($oldCover);
        }

        return back()->with('status', __('ui.saved'));
    }

    public function destroy(Work $work)
    {
        Gate::authorize('delete', $work);
        DB::transaction(function () use ($work) {
            Audit::record('work.archived', $work);
            $work->update(['status' => 'archived']);
            $work->delete();
        });

        return redirect()->route('writer.index')->with('status', __('ui.archived'));
    }

    public function restoreWork(Work $work)
    {
        Gate::authorize('update', $work);
        abort_unless($work->trashed(), 422);
        DB::transaction(function () use ($work) {
            $work->restore();
            $work->update(['status' => 'draft', 'published_at' => null]);
            Audit::record('work.restored', $work);
        });

        return redirect()->route('writer.edit', $work)->with('status', __('ui.restored'));
    }

    public function deleteChapter(Work $work, Chapter $chapter)
    {
        Gate::authorize('update', $work);
        abort_unless($chapter->work_id === $work->id, 404);
        DB::transaction(function () use ($work, $chapter) {
            Work::whereKey($work->id)->lockForUpdate()->firstOrFail();
            Audit::record('chapter.deleted', $chapter);
            $chapter->delete();
        });

        return redirect()->route('writer.chapters', $work)->with('status', __('ui.saved'));
    }

    public function chapters(Work $work)
    {
        Gate::authorize('update', $work);

        return view('writer.chapters', ['work' => $work, 'chapters' => $work->chapters()->get(), 'files' => $work->files()->latest()->get()]);
    }

    public function chapter(Work $work, ?Chapter $chapter = null)
    {
        Gate::authorize('update', $work);
        abort_if($chapter && $chapter->work_id !== $work->id, 404);

        return view('writer.chapter', ['work' => $work, 'chapter' => $chapter ?? new Chapter(['status' => 'draft', 'revision' => 0]), 'versions' => $chapter ? $chapter->versions()->limit(20)->get() : collect()]);
    }

    public function saveChapter(Request $request, Work $work, ChapterWriter $writer, ?Chapter $chapter = null)
    {
        Gate::authorize('update', $work);
        abort_if($chapter && $chapter->work_id !== $work->id, 404);
        $validated = $request->validate(['title' => 'required|string|max:180', 'content' => 'required|string|max:300000', 'status' => 'required|in:draft,published', 'published_at' => 'nullable|date', 'revision' => ($chapter ? 'required' : 'nullable').'|integer|min:0']);
        $chapter = $writer->save($work, $validated, $chapter);
        if ($request->expectsJson()) {
            return response()->json(['revision' => $chapter->revision, 'saved_at' => $chapter->updated_at->toIso8601String(), 'url' => route('writer.chapter.edit', [$work, $chapter])]);
        }

        return redirect()->route('writer.chapter.edit', [$work, $chapter])->with('status', __('ui.saved'));
    }

    public function restore(Request $request, Work $work, Chapter $chapter, ChapterVersion $version, ChapterWriter $writer)
    {
        Gate::authorize('update', $work);
        abort_unless($chapter->work_id === $work->id && $version->chapter_id === $chapter->id, 404);
        $request->validate(['revision' => 'required|integer']);
        $writer->save($work, ['title' => $version->title, 'content' => $version->content, 'revision' => $request->integer('revision'), 'status' => 'draft'], $chapter);

        return back()->with('status', __('ui.restored'));
    }

    public function reorder(Request $request, Work $work)
    {
        Gate::authorize('update', $work);
        $validated = $request->validate(['chapters' => 'required|array', 'chapters.*' => 'required|integer|distinct']);
        $ids = $work->chapters()->pluck('id')->all();
        $submitted = $validated['chapters'];
        sort($ids);
        $compare = $submitted;
        sort($compare);
        abort_unless($ids === $compare, 422);
        DB::transaction(function () use ($work, $submitted) {
            Work::whereKey($work->id)->lockForUpdate()->first();
            $max = $work->chapters()->max('position') + count($submitted) + 1;
            foreach ($submitted as $i => $id) {
                $work->chapters()->whereKey($id)->update(['position' => $max + $i]);
            }foreach ($submitted as $i => $id) {
                $work->chapters()->whereKey($id)->update(['position' => $i + 1]);
            }Audit::record('chapters.reordered', $work);
        });

        return back()->with('status', __('ui.saved'));
    }

    public function upload(Request $request, Work $work, Uploads $uploads)
    {
        Gate::authorize('update', $work);
        $request->validate(['file' => 'required|file|max:'.config('imary.max_file_kb'), 'import' => 'nullable|boolean']);
        $file = $uploads->document($work, $request->file('file'), $request->boolean('import'));
        ScanWorkFile::dispatch($file->id);
        Audit::record('file.uploaded', $file);

        return back()->with('status', __('ui.upload_pending'));
    }

    public function removeFile(Work $work, WorkFile $file)
    {
        Gate::authorize('update', $work);
        abort_unless($file->work_id === $work->id, 404);
        Audit::record('file.removed', $file);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('status', __('ui.saved'));
    }

    public function comments(Request $request)
    {
        Gate::authorize('create', Work::class);

        return view('writer.comments', ['comments' => Comment::whereHas('work', fn ($q) => $q->where('owner_id', $request->user()->id))->with(['work', 'user'])->latest()->paginate(30)]);
    }

    public function followers(Request $request)
    {
        Gate::authorize('create', Work::class);

        return view('writer.followers', ['followers' => $request->user()->author?->followers()->paginate(30)]);
    }
}
