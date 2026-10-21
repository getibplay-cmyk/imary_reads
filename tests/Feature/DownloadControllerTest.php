<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\User;
use App\Models\Work;
use App\Services\EpubImporter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_export_contains_only_published_chapters_and_can_be_disabled_immediately(): void
    {
        $work = Work::factory()->published()->create();
        Chapter::factory()->for($work)->published()->create(['plain_text' => 'النص المسموح']);
        Chapter::factory()->for($work)->create(['position' => 2, 'plain_text' => 'سر المسودة']);
        $url = route('downloads.export', [$work, 'txt']);
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertOk()->assertSee('النص المسموح')->assertDontSee('سر المسودة');
        $this->assertDatabaseCount('download_events', 1);
        $work->update(['allow_download' => false]);
        $this->get($url)->assertForbidden();
        $this->assertDatabaseCount('download_events', 1);
    }

    public function test_generated_epub_round_trips_into_safe_chapters(): void
    {
        $work = Work::factory()->published()->create();
        Chapter::factory()->for($work)->published()->create(['plain_text' => 'مرحبا من مراكش']);
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('downloads.export', [$work, 'epub']))->assertOk()->assertDownload($work->slug.'.epub');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $chapters = app(EpubImporter::class)->read($path);
            $this->assertCount(1, $chapters);
            $this->assertStringContainsString('مرحبا من مراكش', $chapters[0]['content']);
        } finally {
            unlink($path);
        }
    }

    public function test_quarantine_and_foreign_files_are_never_downloadable(): void
    {
        Storage::fake('local');
        $work = Work::factory()->published()->create();
        Storage::disk('local')->put('quarantine/book.txt', 'Private text');
        $file = $work->files()->create(['path' => 'quarantine/book.txt', 'original_name' => 'book.txt', 'mime' => 'text/plain', 'size' => 12, 'sha256' => hash('sha256', 'Private text')]);
        $this->actingAs(User::factory()->create());
        $this->get(route('downloads.file', [$work, $file]))->assertNotFound();
        $file->update(['scan_status' => 'clean']);
        $response = $this->get(route('downloads.file', [$work, $file]))->assertOk()->assertDownload('book.txt');
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $other = Work::factory()->published()->create();
        $this->get(route('downloads.file', [$other, $file]))->assertNotFound();
        Storage::disk('local')->assertExists('quarantine/book.txt');
    }

    public function test_unverified_accounts_and_unconfirmed_rights_cannot_download(): void
    {
        $work = Work::factory()->published()->create(['rights_confirmed' => false]);
        Chapter::factory()->for($work)->published()->create();
        $url = route('downloads.export', [$work, 'txt']);
        $this->actingAs(User::factory()->unverified()->create())->get($url)->assertRedirect('/email/verify');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }
}
