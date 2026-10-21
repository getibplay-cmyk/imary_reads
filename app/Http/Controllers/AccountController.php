<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return view('account.index', ['favorites' => $request->user()->favorites()->publiclyVisible()->with(['author', 'category'])->withAvg('reviews', 'rating')->paginate(12), 'progress' => ReadingProgress::where('user_id', $request->user()->id)->whereHas('chapter', fn ($q) => $q->published())->whereHas('work', fn ($q) => $q->publiclyVisible())->with(['work.author', 'chapter'])->latest('updated_at')->limit(12)->get(), 'bookmarks' => Bookmark::where('user_id', $request->user()->id)->whereHas('chapter', fn ($q) => $q->published())->whereHas('chapter.work', fn ($q) => $q->publiclyVisible())->with('chapter.work')->latest()->limit(30)->get(), 'shelves' => $request->user()->shelves()->with(['works' => fn ($q) => $q->publiclyVisible()->with('author')])->get()]);
    }

    public function locale(Request $request)
    {
        $validated = $request->validate(['locale' => 'required|in:ar,fr,en']);
        $request->session()->put('locale', $validated['locale']);
        $request->user()?->update($validated);

        return back();
    }

    public function settings()
    {
        return view('account.settings');
    }

    public function update(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:120', 'locale' => 'required|in:ar,fr,en', 'bio' => 'nullable|string|max:3000']);
        $request->user()->update(collect($validated)->only(['name', 'locale'])->all());
        $request->session()->put('locale', $validated['locale']);
        $request->user()->author?->update(['name' => $validated['name'], 'bio' => $validated['bio'] ?? null]);

        return back()->with('status', __('ui.saved'));
    }

    public function becomeWriter(Request $request)
    {
        abort_unless($request->user()->role === 'reader', 422);
        DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->role === 'reader', 422);
            $user->forceFill(['role' => 'author'])->save();
            Author::firstOrCreate(['user_id' => $user->id], ['name' => $user->name, 'slug' => 'author-'.Str::ulid()]);
        });

        return redirect()->route('writer.index');
    }

    public function notifications(Request $request)
    {
        return view('account.notifications', ['notifications' => $request->user()->notifications()->paginate(30)]);
    }

    public function readNotifications(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function export(Request $request)
    {
        $user = $request->user();

        return response()->streamDownload(function () use ($user) {
            echo json_encode(['account' => $user->only(['name', 'email', 'locale', 'created_at']), 'works' => $user->works()->with('chapters')->get()->makeHidden(['rights_note']), 'favorites' => $user->favorites()->pluck('title'), 'progress' => ReadingProgress::where('user_id', $user->id)->get(), 'bookmarks' => Bookmark::where('user_id', $user->id)->get(), 'comments' => Comment::where('user_id', $user->id)->get()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }, 'imary-account.json', ['Content-Type' => 'application/json']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);
        abort_if($request->user()->isStaff(), 403);
        $user = $request->user();
        Auth::logout();
        DB::transaction(function () use ($user) {
            $user->works()->update(['status' => 'archived', 'allow_download' => false]);
            $user->author?->update(['name' => __('ui.deleted_user'), 'bio' => null]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
