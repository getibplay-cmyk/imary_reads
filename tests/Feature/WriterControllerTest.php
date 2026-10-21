<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\ReadingProgress;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WriterControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function payload(): array
    {
        return ['title' => 'رحلة جديدة', 'synopsis' => 'قصة أصلية عن المدينة والذاكرة والبحث عن الحقيقة.', 'category_id' => Category::factory()->create()->id, 'language' => 'ar', 'kind' => 'novel', 'completion' => 'ongoing', 'license' => 'all_rights_reserved', 'rights_confirmed' => 1, 'allow_comments' => 1];
    }

    public function test_guests_unverified_and_readers_cannot_use_writer_tools(): void
    {
        $this->get('/writer')->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create(['role' => 'author']))->get('/writer')->assertRedirect('/email/verify');
        $this->actingAs(User::factory()->create())->get('/writer')->assertForbidden();
    }

    public function test_author_creates_own_work_without_escalating_publication_or_ownership(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create();
        $payload = $this->payload() + ['owner_id' => $other->id, 'status' => 'published', 'featured' => true];
        $this->actingAs($author)->post(route('writer.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('works', ['owner_id' => $author->id, 'status' => 'draft', 'featured' => false, 'title' => 'رحلة جديدة']);
        $this->assertDatabaseCount('authors', 1);
    }

    public function test_published_metadata_edit_returns_to_moderation(): void
    {
        $work = Work::factory()->published()->create();
        $this->actingAs($work->owner)->put(route('writer.update', $work), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('pending', $work->fresh()->status);
    }

    public function test_cross_author_edits_and_cross_work_chapters_are_rejected(): void
    {
        $work = Work::factory()->create();
        $other = Work::factory()->create();
        $chapter = Chapter::factory()->for($other)->create();
        $this->actingAs($other->owner)->get(route('writer.edit', $work))->assertForbidden();
        $this->actingAs($work->owner)->get(route('writer.chapter.edit', [$work, $chapter]))->assertNotFound();
    }

    public function test_chapter_save_strips_active_html_and_creates_version(): void
    {
        $work = Work::factory()->create();
        $this->actingAs($work->owner)->postJson(route('writer.chapter.store', $work), ['title' => 'فصل', 'content' => '<p onclick="alert(1)">النص <strong>الجميل</strong><script>alert(2)</script><img src=x onerror=alert(3)></p>', 'status' => 'draft'])->assertOk()->assertJsonPath('revision', 1);
        $chapter = $work->chapters()->first();
        $this->assertStringContainsString('<strong>الجميل</strong>', $chapter->content);
        $this->assertStringNotContainsString('onclick', $chapter->content);
        $this->assertStringNotContainsString('<script', $chapter->content);
        $this->assertStringNotContainsString('<img', $chapter->content);
        $this->assertDatabaseHas('chapter_versions', ['chapter_id' => $chapter->id, 'revision' => 1]);
    }

    public function test_long_arabic_chapter_is_not_silently_truncated(): void
    {
        $work = Work::factory()->create();
        $text = str_repeat('حكاية عربية طويلة. ', 2500).'النهاية';
        $this->actingAs($work->owner)->postJson(route('writer.chapter.store', $work), ['title' => 'فصل طويل', 'content' => '<div><p>'.$text.'</p></div>', 'status' => 'draft'])->assertOk();
        $this->assertSame($text, $work->chapters()->first()->plain_text);
    }

    public function test_stale_editor_cannot_overwrite_new_revision(): void
    {
        $chapter = Chapter::factory()->create();
        $work = $chapter->work;
        $this->actingAs($work->owner)->putJson(route('writer.chapter.update', [$work, $chapter]), ['title' => 'محدث', 'content' => '<p>النص الجديد</p>', 'status' => 'draft', 'revision' => 1])->assertOk()->assertJsonPath('revision', 2);
        $this->putJson(route('writer.chapter.update', [$work, $chapter]), ['title' => 'قديم', 'content' => '<p>النص القديم</p>', 'status' => 'draft', 'revision' => 1])->assertConflict();
        $this->assertSame('محدث', $chapter->fresh()->title);
        $this->assertDatabaseCount('chapter_versions', 1);
    }

    public function test_restoration_creates_new_draft_revision_without_erasing_history(): void
    {
        $chapter = Chapter::factory()->published()->create();
        $version = $chapter->versions()->create(['revision' => 1, 'title' => 'نسخة أصلية', 'content' => '<p>نص أصلي</p>', 'created_at' => now()]);
        $this->actingAs($chapter->work->owner)->post(route('writer.chapter.restore', [$chapter->work, $chapter, $version]), ['revision' => 1])->assertRedirect();
        $this->assertDatabaseHas('chapters', ['id' => $chapter->id, 'revision' => 2, 'status' => 'draft', 'title' => 'نسخة أصلية']);
        $this->assertDatabaseCount('chapter_versions', 2);
    }

    public function test_reordering_requires_complete_set_and_preserves_positions(): void
    {
        $work = Work::factory()->create();
        $a = Chapter::factory()->for($work)->create();
        $b = Chapter::factory()->for($work)->create(['position' => 2]);
        $this->actingAs($work->owner)->post(route('writer.chapters.reorder', $work), ['chapters' => [$b->id, $a->id]])->assertRedirect();
        $this->assertSame([$b->id, $a->id], $work->chapters()->pluck('id')->all());
        $this->postJson(route('writer.chapters.reorder', $work), ['chapters' => [$a->id]])->assertUnprocessable();
    }

    public function test_writer_pages_render_with_work_chapters_and_comments(): void
    {
        $work = Work::factory()->create();
        $chapter = Chapter::factory()->for($work)->create();
        $this->actingAs($work->owner);
        foreach ([route('writer.index'), route('writer.create'), route('writer.edit', $work), route('writer.chapters', $work), route('writer.chapter.create', $work), route('writer.chapter.edit', [$work, $chapter]), route('writer.comments'), route('writer.followers')] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_archiving_withdraws_work_from_readers(): void
    {
        $work = Work::factory()->published()->create();
        $this->actingAs($work->owner)->delete(route('writer.destroy', $work))->assertRedirect(route('writer.index'));
        $this->assertSoftDeleted($work);
        $this->get(route('works.show', $work))->assertNotFound();
    }

    public function test_archived_work_can_be_restored_as_draft_only_by_owner(): void
    {
        $work = Work::factory()->published()->create();
        $work->delete();
        $this->actingAs(User::factory()->create(['role' => 'author']))->post(route('writer.restore', $work))->assertForbidden();
        $this->actingAs($work->owner)->get(route('writer.index', ['status' => 'archived']))->assertOk()->assertSee($work->title);
        $this->post(route('writer.restore', $work))->assertRedirect(route('writer.edit', $work));
        $this->assertNull($work->fresh()->deleted_at);
        $this->assertSame('draft', $work->fresh()->status);
    }

    public function test_deleting_a_chapter_removes_dependent_progress_and_denies_foreign_owner(): void
    {
        $chapter = Chapter::factory()->published()->create();
        $work = $chapter->work;
        $reader = User::factory()->create();
        ReadingProgress::create(['user_id' => $reader->id, 'work_id' => $work->id, 'chapter_id' => $chapter->id, 'offset' => 0, 'mode' => 'book']);
        $this->actingAs($reader)->delete(route('writer.chapter.delete', [$work, $chapter]))->assertForbidden();
        $this->actingAs($work->owner)->delete(route('writer.chapter.delete', [$work, $chapter]))->assertRedirect(route('writer.chapters', $work));
        $this->assertModelMissing($chapter);
        $this->assertDatabaseCount('reading_progress', 0);
    }
}
