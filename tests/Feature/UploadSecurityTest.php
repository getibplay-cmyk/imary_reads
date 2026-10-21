<?php

namespace Tests\Feature;

use App\Jobs\ScanWorkFile;
use App\Models\Chapter;
use App\Models\Work;
use App\Models\WorkFile;
use App\Services\BookExport;
use App\Services\EpubImporter;
use App\Services\Uploads;
use App\Services\VirusScanner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function textFile(Work $work): WorkFile
    {
        Storage::disk('local')->put('quarantine/test.txt', 'نص أصلي آمن');

        return $work->files()->create(['path' => 'quarantine/test.txt', 'original_name' => 'chapter.txt', 'mime' => 'text/plain', 'size' => 20, 'sha256' => hash('sha256', 'نص أصلي آمن'), 'import_requested' => true]);
    }

    public function test_upload_is_queued_privately_until_scanned(): void
    {
        Storage::fake('local');
        Queue::fake([ScanWorkFile::class]);
        $work = Work::factory()->create();
        $this->actingAs($work->owner)->post(route('writer.files.store', $work), ['file' => UploadedFile::fake()->createWithContent('chapter.txt', 'نص أصلي آمن'), 'import' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $file = $work->files()->first();
        $this->assertSame('pending', $file->scan_status);
        Storage::disk('local')->assertExists($file->path);
        Queue::assertPushed(ScanWorkFile::class, fn ($job) => $job->fileId === $file->id);
        $this->assertDatabaseCount('chapters', 0);
    }

    public function test_executable_and_spoofed_extension_are_rejected(): void
    {
        Queue::fake([ScanWorkFile::class]);
        $work = Work::factory()->create();
        $this->actingAs($work->owner);
        foreach (['script.php', 'document.pdf'] as $name) {
            $path = tempnam(sys_get_temp_dir(), 'spoof-');
            file_put_contents($path, '<?php echo "bad";');
            try {
                $this->postJson(route('writer.files.store', $work), ['file' => new UploadedFile($path, $name, null, null, true)])->assertUnprocessable()->assertJsonValidationErrors('file');
            } finally {
                unlink($path);
            }
        }
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('work_files', 0);
    }

    public function test_non_utf8_text_is_rejected_before_import_can_corrupt_content(): void
    {
        Queue::fake([ScanWorkFile::class]);
        $work = Work::factory()->create();
        $this->actingAs($work->owner)->postJson(route('writer.files.store', $work), [
            'file' => UploadedFile::fake()->createWithContent('chapter.txt', "Text with invalid byte: \xE9"),
            'import' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('work_files', 0);
    }

    public function test_clean_import_is_idempotent_and_creates_only_drafts(): void
    {
        Storage::fake('local');
        $work = Work::factory()->create();
        $file = $this->textFile($work);
        $scanner = $this->mock(VirusScanner::class, fn ($mock) => $mock->shouldReceive('clean')->once()->andReturn(true));
        $job = new ScanWorkFile($file->id);
        $job->handle($scanner);
        $job->handle($scanner);
        $this->assertSame('clean', $file->fresh()->scan_status);
        $this->assertNotNull($file->fresh()->imported_at);
        $this->assertDatabaseCount('chapters', 1);
        $this->assertDatabaseHas('chapters', ['work_id' => $work->id, 'status' => 'draft']);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_infected_file_is_rejected_and_deleted_without_import(): void
    {
        Storage::fake('local');
        $file = $this->textFile(Work::factory()->create());
        $scanner = $this->mock(VirusScanner::class, fn ($mock) => $mock->shouldReceive('clean')->once()->andReturn(false));
        (new ScanWorkFile($file->id))->handle($scanner);
        $this->assertSame('rejected', $file->fresh()->scan_status);
        $this->assertDatabaseCount('chapters', 0);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_scanner_failure_leaves_file_quarantined(): void
    {
        Storage::fake('local');
        $file = $this->textFile(Work::factory()->create());
        config(['imary.scanner' => '/nonexistent/clamav']);
        try {
            (new ScanWorkFile($file->id))->handle(app(VirusScanner::class));
            $this->fail('A missing scanner must fail closed.');
        } catch (\RuntimeException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertSame('pending', $file->fresh()->scan_status);
        $this->assertDatabaseCount('chapters', 0);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_modified_file_is_not_marked_clean(): void
    {
        Storage::fake('local');
        $file = $this->textFile(Work::factory()->create());
        Storage::disk('local')->put($file->path, 'Changed after upload');
        $scanner = $this->mock(VirusScanner::class, fn ($m) => $m->shouldReceive('clean')->once()->andReturn(true));
        try {
            (new ScanWorkFile($file->id))->handle($scanner);
            $this->fail('Integrity check must reject changed bytes.');
        } catch (\RuntimeException $e) {
            $this->assertSame('File integrity mismatch', $e->getMessage());
        }
        $this->assertSame('pending', $file->fresh()->scan_status);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_cover_is_reencoded_as_private_jpeg(): void
    {
        Storage::fake('local');
        $path = app(Uploads::class)->cover(UploadedFile::fake()->image('cover.png', 600, 900));
        Storage::disk('local')->assertExists($path);
        $this->assertSame('image/jpeg', mime_content_type(Storage::disk('local')->path($path)));
        $this->assertStringStartsWith('covers/', $path);
    }

    public static function unsafeArchives(): array
    {
        return ['path traversal' => ['path'], 'external entity' => ['entity'], 'compression bomb' => ['bomb'], 'encoded traversal' => ['encoded']];
    }

    #[DataProvider('unsafeArchives')]
    public function test_unsafe_epub_archives_are_rejected(string $attack): void
    {
        $work = Work::factory()->published()->create();
        Chapter::factory()->for($work)->published()->create();
        $path = app(BookExport::class)->epub($work);
        $zip = new \ZipArchive;
        $zip->open($path);
        if ($attack === 'path') {
            $zip->addFromString('../outside', 'bad');
        }
        if ($attack === 'entity') {
            $zip->addFromString('META-INF/container.xml', '<!DOCTYPE x [<!ENTITY ext SYSTEM "file:///etc/passwd">]><container>&ext;</container>');
        }
        if ($attack === 'bomb') {
            $zip->addFromString('bomb.txt', str_repeat('A', 1024 * 1024));
        }
        if ($attack === 'encoded') {
            $opf = $zip->getFromName('OEBPS/content.opf');
            $zip->addFromString('OEBPS/content.opf', str_replace('href="ch0.xhtml"', 'href="%2e%2e/secret.xhtml"', $opf));
        }
        $zip->close();
        try {
            $this->expectException(ValidationException::class);
            app(EpubImporter::class)->read($path);
        } finally {
            unlink($path);
        }
    }
}
