<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\ReadingProgress;
use App\Models\Report;
use App\Models\Review;
use App\Models\Shelf;
use App\Models\Work;
use App\Services\Audit;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    private function publicWork(Work $work): void
    {
        abort_unless($work->isPublic(), 404);
    }

    public function comment(Request $request, Work $work)
    {
        $this->publicWork($work);
        abort_unless($work->allow_comments, 403);
        $validated = $request->validate(['body' => 'required|string|min:2|max:3000', 'parent_id' => 'nullable|integer']);
        if (! empty($validated['parent_id'])) {
            abort_unless($work->comments()->whereKey($validated['parent_id'])->whereNull('parent_id')->where('status', 'visible')->exists(), 422);
        }$work->comments()->create($validated + ['user_id' => $request->user()->id]);

        return back()->with('status', __('ui.comment_added'));
    }

    public function moderate(Request $request, Comment $comment)
    {
        $request->validate(['status' => 'required|in:visible,hidden']);
        $owner = $comment->work->owner_id;
        abort_unless($request->user()->id === $comment->user_id || $request->user()->id === $owner || $request->user()->isStaff(), 403);
        abort_if($request->user()->id === $comment->user_id && $request->input('status') === 'visible' && ! $request->user()->isStaff() && $request->user()->id !== $owner, 403);
        $comment->update(['status' => $request->input('status')]);
        Audit::record('comment.moderated', $comment);

        return back()->with('status', __('ui.saved'));
    }

    public function review(Request $request, Work $work)
    {
        $this->publicWork($work);
        abort_if($work->owner_id === $request->user()->id, 403);
        $validated = $request->validate(['rating' => 'required|integer|between:1,5', 'body' => 'nullable|string|max:3000']);
        Review::updateOrCreate(['work_id' => $work->id, 'user_id' => $request->user()->id], $validated);

        return back()->with('status', __('ui.saved'));
    }

    public function favorite(Request $request, Work $work)
    {
        $this->publicWork($work);
        $request->validate(['enabled' => 'required|boolean']);
        $request->boolean('enabled') ? $request->user()->favorites()->syncWithoutDetaching([$work->id]) : $request->user()->favorites()->detach($work->id);

        return back()->with('status', __('ui.saved'));
    }

    public function follow(Request $request, Author $author)
    {
        abort_if($author->user_id === $request->user()->id, 422);
        $request->validate(['enabled' => 'required|boolean']);
        $request->boolean('enabled') ? $author->followers()->syncWithoutDetaching([$request->user()->id]) : $author->followers()->detach($request->user()->id);

        return back()->with('status', __('ui.saved'));
    }

    public function progress(Request $request, Work $work)
    {
        $this->publicWork($work);
        $validated = $request->validate(['chapter_id' => 'required|integer', 'offset' => 'required|integer|min:0|max:1000000', 'mode' => 'required|in:scroll,book']);
        $chapter = $work->chapters()->published()->findOrFail($validated['chapter_id']);
        $validated['offset'] = min($validated['offset'], mb_strlen($chapter->plain_text));
        ReadingProgress::updateOrCreate(['user_id' => $request->user()->id, 'work_id' => $work->id], $validated);

        return response()->json(['saved' => true]);
    }

    public function bookmark(Request $request, Work $work)
    {
        $this->publicWork($work);
        $validated = $request->validate(['chapter_id' => 'required|integer', 'offset' => 'required|integer|min:0|max:1000000', 'note' => 'nullable|string|max:250']);
        $chapter = $work->chapters()->published()->findOrFail($validated['chapter_id']);
        $validated['offset'] = min($validated['offset'], mb_strlen($chapter->plain_text));
        Bookmark::updateOrCreate(['user_id' => $request->user()->id, 'chapter_id' => $chapter->id, 'offset' => $validated['offset']], ['note' => $validated['note'] ?? null]);

        return $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('status', __('ui.saved'));
    }

    public function removeBookmark(Request $request, Bookmark $bookmark)
    {
        abort_unless($bookmark->user_id === $request->user()->id, 403);
        $bookmark->delete();

        return back()->with('status', __('ui.saved'));
    }

    public function report(Request $request, Work $work)
    {
        $this->publicWork($work);
        $validated = $request->validate(['reason' => 'required|in:copyright,abuse,spam,other', 'details' => 'required|string|min:10|max:3000', 'comment_id' => 'nullable|integer']);
        if (! empty($validated['comment_id'])) {
            abort_unless($work->comments()->whereKey($validated['comment_id'])->exists(), 422);
        }Report::create($validated + ['user_id' => $request->user()->id, 'work_id' => $work->id]);

        return back()->with('status', __('ui.report_sent'));
    }

    public function shelf(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:80']);
        $request->user()->shelves()->firstOrCreate($validated);

        return back()->with('status', __('ui.saved'));
    }

    public function shelfWork(Request $request, Shelf $shelf, Work $work)
    {
        abort_unless($shelf->user_id === $request->user()->id, 403);
        $this->publicWork($work);
        $request->validate(['enabled' => 'required|boolean']);
        $request->boolean('enabled') ? $shelf->works()->syncWithoutDetaching([$work->id]) : $shelf->works()->detach($work->id);

        return back()->with('status', __('ui.saved'));
    }

    public function removeShelf(Request $request, Shelf $shelf)
    {
        abort_unless($shelf->user_id === $request->user()->id, 403);
        $shelf->delete();

        return back()->with('status', __('ui.saved'));
    }
}
