<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_comments_require_verification_and_respect_author_switch(): void
    {
        $work = Work::factory()->published()->create(['allow_comments' => false]);
        $this->actingAs(User::factory()->unverified()->create())->post(route('comments.store', $work), ['body' => 'تعليق'])->assertRedirect('/email/verify');
        $this->actingAs(User::factory()->create())->post(route('comments.store', $work), ['body' => 'تعليق'])->assertForbidden();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_and_reply_are_saved_escaped_and_scoped_to_the_work(): void
    {
        $work = Work::factory()->published()->create();
        $user = User::factory()->create();
        $body = '<script>alert(1)</script>';
        $this->actingAs($user)->post(route('comments.store', $work), ['body' => $body])->assertRedirect();
        $parent = Comment::first();
        $this->get(route('works.show', $work))->assertOk()->assertSee($body)->assertDontSee($body, false);
        $this->post(route('comments.store', $work), ['body' => 'رد على التعليق', 'parent_id' => $parent->id])->assertRedirect();
        $other = Work::factory()->published()->create();
        $this->postJson(route('comments.store', $other), ['body' => 'رد خاطئ', 'parent_id' => $parent->id])->assertUnprocessable();
        $this->assertDatabaseCount('comments', 2);
    }

    public function test_readers_cannot_restore_moderated_comments_or_moderate_others(): void
    {
        $work = Work::factory()->published()->create();
        $user = User::factory()->create();
        $comment = $work->comments()->create(['user_id' => $user->id, 'body' => 'تعليق مخفي', 'status' => 'hidden']);
        $this->actingAs($user)->patch(route('comments.moderate', $comment), ['status' => 'visible'])->assertForbidden();
        $this->actingAs(User::factory()->create())->patch(route('comments.moderate', $comment), ['status' => 'hidden'])->assertForbidden();
        $this->actingAs($work->owner)->patch(route('comments.moderate', $comment), ['status' => 'visible'])->assertRedirect();
        $this->assertSame('visible', $comment->fresh()->status);
    }

    public function test_reviews_are_unique_and_author_cannot_rate_own_work(): void
    {
        $work = Work::factory()->published()->create();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('reviews.store', $work), ['rating' => 4])->assertRedirect();
        $this->post(route('reviews.store', $work), ['rating' => 5])->assertRedirect();
        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'rating' => 5]);
        $this->actingAs($work->owner)->post(route('reviews.store', $work), ['rating' => 5])->assertForbidden();
    }

    public function test_favorites_and_follows_are_idempotent_and_reversible(): void
    {
        $work = Work::factory()->published()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post(route('favorites', $work), ['enabled' => 1])->assertRedirect();
        $this->post(route('favorites', $work), ['enabled' => 1])->assertRedirect();
        $this->assertDatabaseCount('favorites', 1);
        $this->post(route('follows', $work->author), ['enabled' => 1])->assertRedirect();
        $this->post(route('follows', $work->author), ['enabled' => 1])->assertRedirect();
        $this->assertDatabaseCount('follows', 1);
        $this->post(route('favorites', $work), ['enabled' => 0])->assertRedirect();
        $this->post(route('follows', $work->author), ['enabled' => 0])->assertRedirect();
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_progress_uses_unicode_offsets_and_rejects_foreign_chapter(): void
    {
        $work = Work::factory()->published()->create();
        $chapter = Chapter::factory()->for($work)->published()->create(['plain_text' => 'مرحبا 📚']);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('progress', $work), ['chapter_id' => $chapter->id, 'offset' => 999, 'mode' => 'book'])->assertExactJson(['saved' => true]);
        $this->assertDatabaseHas('reading_progress', ['user_id' => $user->id, 'work_id' => $work->id, 'offset' => 7, 'mode' => 'book']);
        $foreign = Chapter::factory()->published()->create();
        $this->postJson(route('progress', $work), ['chapter_id' => $foreign->id, 'offset' => 2, 'mode' => 'scroll'])->assertNotFound();
        $this->get(route('reader', $work))->assertOk()->assertSee('data-offset="7"', false)->assertSee('data-mode="book"', false);
    }

    public function test_bookmarks_and_shelves_are_private(): void
    {
        $work = Work::factory()->published()->create();
        $chapter = Chapter::factory()->for($work)->published()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->postJson(route('bookmarks.store', $work), ['chapter_id' => $chapter->id, 'offset' => 2, 'note' => 'ملاحظة خاصة'])->assertOk();
        $this->post(route('shelves.store'), ['name' => 'رف خاص'])->assertRedirect();
        $shelf = $user->shelves()->first();
        $this->post(route('shelves.work', [$shelf, $work]), ['enabled' => 1])->assertRedirect();
        $this->get('/me')->assertOk()->assertSee('رف خاص');
        $this->actingAs(User::factory()->create())->delete(route('bookmarks.delete', Bookmark::first()))->assertForbidden();
        $this->post(route('shelves.work', [$shelf, $work]), ['enabled' => 0])->assertForbidden();
        $this->get('/me')->assertOk()->assertDontSee('رف خاص');
    }

    public function test_report_is_recorded_for_moderators(): void
    {
        $work = Work::factory()->published()->create();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('reports.store', $work), ['reason' => 'copyright', 'details' => 'أرجو مراجعة حقوق نشر هذا النص.', 'status' => 'resolved'])->assertRedirect();
        $this->assertDatabaseHas('reports', ['work_id' => $work->id, 'user_id' => $user->id, 'status' => 'open', 'reason' => 'copyright']);
    }
}
