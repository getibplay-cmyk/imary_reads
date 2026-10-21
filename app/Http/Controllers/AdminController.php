<?php

namespace App\Http\Controllers;

use App\Jobs\ScanWorkFile;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Category;
use App\Models\DownloadEvent;
use App\Models\Post;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkFile;
use App\Models\WorkView;
use App\Services\Audit;
use App\Services\HtmlContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', ['counts' => ['users' => User::count(), 'works' => Work::count(), 'pending' => Work::where('status', 'pending')->count(), 'reports' => Report::where('status', 'open')->count(), 'views' => WorkView::count(), 'downloads' => DownloadEvent::count()], 'works' => Work::with(['author', 'owner'])->latest()->paginate(20), 'reports' => Report::with(['user', 'work'])->where('status', 'open')->latest()->limit(30)->get()]);
    }

    public function moderate(Request $request, Work $work)
    {
        $validated = $request->validate(['status' => 'required|in:published,rejected,archived,pending', 'moderation_note' => 'nullable|string|max:2000', 'featured' => 'nullable|boolean']);
        if ($validated['status'] === 'published') {
            abort_unless($work->rights_confirmed, 422, __('ui.rights_required'));
            abort_unless($work->chapters()->published()->exists() || ($work->allow_download && $work->files()->where('scan_status', 'clean')->exists()), 422, __('ui.publish_content_required'));
        }$validated['featured'] = $request->boolean('featured');
        if ($validated['status'] === 'published') {
            $validated['published_at'] = $work->published_at ?? now();
        }DB::transaction(function () use ($work, $validated) {
            $work->update($validated);
            Audit::record('work.moderated', $work, ['status' => $validated['status']]);
        });

        return back()->with('status', __('ui.saved'));
    }

    public function resolve(Request $request, Report $report)
    {
        $validated = $request->validate(['status' => 'required|in:resolved,dismissed', 'resolution' => 'required|string|min:5|max:2000']);
        DB::transaction(function () use ($report, $validated) {
            $report->update($validated);
            Audit::record('report.resolved', $report, ['status' => $validated['status']]);
        });

        return back()->with('status', __('ui.saved'));
    }

    public function users(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $request->validate(['q' => 'nullable|string|max:120']);

        return view('admin.users', ['users' => User::query()->when($request->filled('q'), fn ($q) => $q->where('email', 'like', '%'.$request->input('q').'%'))->latest()->paginate(25)]);
    }

    public function user(Request $request, User $user)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $validated = $request->validate(['role' => 'required|in:reader,author,moderator,admin', 'banned' => 'nullable|boolean']);
        abort_if($user->id === $request->user()->id && ($validated['role'] !== 'admin' || $request->boolean('banned')), 422);
        DB::transaction(function () use ($request, $user, $validated) {
            $admins = User::where('role', 'admin')->whereNull('banned_at')->lockForUpdate()->get();
            abort_if($user->role === 'admin' && ($validated['role'] !== 'admin' || $request->boolean('banned')) && $admins->count() <= 1, 422);
            $user->forceFill(['role' => $validated['role'], 'banned_at' => $request->boolean('banned') ? now() : null])->save();
            if ($validated['role'] === 'author') {
                Author::firstOrCreate(['user_id' => $user->id], ['name' => $user->name, 'slug' => 'author-'.Str::ulid()]);
            }DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record('user.access_updated', $user, ['role' => $validated['role'], 'banned' => $request->boolean('banned')]);
        });

        return back()->with('status', __('ui.saved'));
    }

    public function taxonomy()
    {
        return view('admin.taxonomy', ['categories' => Category::all(), 'authors' => Author::with('user')->latest()->paginate(20)]);
    }

    public function category(Request $request, ?Category $category = null)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $validated = $request->validate(['slug' => 'required|alpha_dash|max:80|unique:categories,slug,'.($category?->id ?? 'NULL'), 'name.ar' => 'required|string|max:80', 'name.fr' => 'required|string|max:80', 'name.en' => 'required|string|max:80']);
        $category ??= new Category;
        $category->fill($validated)->save();
        Audit::record('category.saved', $category);

        return back()->with('status', __('ui.saved'));
    }

    public function author(Request $request, ?Author $author = null)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $validated = $request->validate(['name' => 'required|string|max:120', 'bio' => 'nullable|string|max:3000']);
        if (! $author) {
            $validated['slug'] = 'author-'.Str::ulid();
        }$author ??= new Author;
        $author->fill($validated)->save();
        Audit::record('author.saved', $author);

        return back()->with('status', __('ui.saved'));
    }

    public function posts()
    {
        return view('admin.posts', ['posts' => Post::latest()->paginate(15)]);
    }

    public function post(Request $request, HtmlContent $html, ?Post $post = null)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $validated = $request->validate(['title.ar' => 'required|string|max:180', 'title.fr' => 'required|string|max:180', 'title.en' => 'required|string|max:180', 'body.ar' => 'required|string|max:50000', 'body.fr' => 'required|string|max:50000', 'body.en' => 'required|string|max:50000', 'status' => 'required|in:draft,published']);
        foreach ($validated['body'] as $locale => $body) {
            $validated['body'][$locale] = $html->clean($body);
        }if (! $post) {
            $validated['slug'] = 'post-'.Str::lower(Str::random(12));
        }$validated['published_at'] = $validated['status'] === 'published' ? now() : null;
        $post ??= new Post;
        $post->fill($validated)->save();
        Audit::record('post.saved', $post);

        return back()->with('status', __('ui.saved'));
    }

    public function files()
    {
        return view('admin.files', ['files' => WorkFile::with('work')->latest()->paginate(30)]);
    }

    public function retry(Request $request, WorkFile $file)
    {
        abort_unless($file->scan_status === 'pending', 422);
        ScanWorkFile::dispatch($file->id);
        Audit::record('file.scan_retried', $file);

        return back()->with('status', __('ui.upload_pending'));
    }

    public function audit()
    {
        return view('admin.audit', ['logs' => AuditLog::with('user')->latest('created_at')->paginate(50)]);
    }

    public function settings()
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        return view('admin.settings', ['settings' => Setting::pluck('value', 'key')]);
    }

    public function saveSettings(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $validated = $request->validate(['contact_email' => 'nullable|email|max:255', 'announcement' => 'nullable|string|max:500']);
        foreach ($validated as $key => $value) {
            $s = Setting::updateOrCreate(['key' => $key], ['value' => ['text' => $value]]);
            Audit::record('setting.updated', $s, ['key' => $key]);
        }

        return back()->with('status', __('ui.saved'));
    }
}
