<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CatalogControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_default_home_is_arabic_and_public_pages_render(): void
    {
        $work = Work::factory()->published()->create(['featured' => true]);
        Chapter::factory()->for($work)->published()->create();
        foreach (['/', '/library', '/categories', '/writers', '/blog', '/legal/privacy', '/legal/copyright', '/legal/terms', '/login', '/register', '/forgot-password', route('authors.show', $work->author), route('works.show', $work), route('reader', $work)] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false);
    }

    public function test_search_excludes_drafts_future_releases_and_banned_owners(): void
    {
        $visible = Work::factory()->published()->create(['title' => 'أصداء مراكش']);
        Work::factory()->create(['title' => 'أصداء سرية']);
        Work::factory()->published()->create(['title' => 'أصداء المستقبل', 'published_at' => now()->addDay()]);
        Work::factory()->published()->for(User::factory()->state(['banned_at' => now()]), 'owner')->create(['title' => 'أصداء محجوبة']);
        $this->getJson('/search?q='.urlencode('أصداء'))->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.title', $visible->title);
    }

    public function test_search_treats_wildcards_literally_and_rejects_sort_injection(): void
    {
        Work::factory()->published()->create(['title' => '100% أمل']);
        Work::factory()->published()->create(['title' => '1000 أمل']);
        $this->getJson('/search?q=100%25')->assertOk()->assertJsonCount(1, 'results');
        $this->getJson('/search?q=a')->assertExactJson(['results' => []]);
        $this->getJson('/search?q=city&sort=title%3BDROP')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_catalogue_filters_by_language_and_category(): void
    {
        $work = Work::factory()->published()->create(['language' => 'fr']);
        Work::factory()->published()->create(['title' => 'مستبعد']);
        $this->get('/library?language=fr&category='.$work->category->slug)->assertOk()->assertSee($work->title)->assertDontSee('مستبعد');
    }

    public function test_reader_denies_draft_future_and_foreign_chapters(): void
    {
        $work = Work::factory()->published()->create();
        $draft = Chapter::factory()->for($work)->create();
        $future = Chapter::factory()->for($work)->published()->create(['position' => 2, 'published_at' => now()->addDay()]);
        $foreign = Chapter::factory()->published()->create();
        Chapter::factory()->for($work)->published()->create(['position' => 3]);
        foreach ([$draft, $future, $foreign] as $chapter) {
            $this->get(route('reader', [$work, $chapter->id]))->assertNotFound();
        }
    }

    public function test_draft_work_is_hidden_but_owner_can_preview(): void
    {
        $work = Work::factory()->create();
        Chapter::factory()->for($work)->create();
        $this->get(route('works.show', $work))->assertForbidden();
        $this->actingAs($work->owner)->get(route('reader', $work))->assertOk();
    }

    public function test_repeat_reads_count_once_per_reader_per_day(): void
    {
        $this->freezeTime();
        $work = Work::factory()->published()->create();
        Chapter::factory()->for($work)->published()->create();
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('reader', $work))->assertOk();
        $this->get(route('reader', $work))->assertOk();
        $this->assertDatabaseCount('work_views', 1);
        $this->travel(1)->days();
        $this->get(route('reader', $work))->assertOk();
        $this->assertDatabaseCount('work_views', 2);
    }

    public function test_security_headers_apply_and_user_content_is_escaped(): void
    {
        $work = Work::factory()->published()->create(['title' => '<img src=x onerror=alert(1)>']);
        $response = $this->get(route('works.show', $work));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertSee($work->title)->assertDontSee($work->title, false);
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
    }
}
