<?php

namespace App\Console\Commands;

use App\Services\VirusScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;

class Doctor extends Command
{
    protected $signature = 'imary:doctor {--production} {--scan : Run a real clean-file scan}';

    protected $description = 'Check deployment configuration without exposing secrets';

    public function handle(): int
    {
        $checks = ['Application key' => strlen((string) config('app.key')) > 20, 'Private storage writable' => is_writable(storage_path('app/private')), 'Scanner executable' => (new ExecutableFinder)->find(config('imary.scanner')) !== null];
        try {
            DB::connection()->getPdo();
            $checks['Database connection'] = true;
        } catch (\Throwable) {
            $checks['Database connection'] = false;
        }
        if ($this->option('production')) {
            $checks += ['Production environment' => app()->environment('production'), 'Debug disabled' => ! config('app.debug'), 'HTTPS URL' => str_starts_with(config('app.url'), 'https://'), 'Secure cookies' => (bool) config('session.secure'), 'Real mail transport' => ! in_array(config('mail.default'), ['log', 'array'], true), 'Durable queue' => config('queue.default') !== 'sync', 'Database sessions' => config('session.driver') === 'database', 'Encrypted sessions' => (bool) config('session.encrypt'), 'HTTP-only cookies' => (bool) config('session.http_only'), 'Contact address' => filter_var(config('imary.contact_email'), FILTER_VALIDATE_EMAIL) !== false, 'PostgreSQL' => config('database.default') === 'pgsql', 'Assets built' => file_exists(public_path('build/manifest.json'))];
        }
        if ($this->option('scan')) {
            $path = tempnam(sys_get_temp_dir(), 'imary-check-');
            try {
                file_put_contents($path, 'Imary Reads scanner health check.');
                $checks['Scanner and signatures'] = app(VirusScanner::class)->clean($path);
            } catch (\Throwable) {
                $checks['Scanner and signatures'] = false;
            } finally {
                unlink($path);
            }
        }
        foreach ($checks as $label => $ok) {
            $this->line(($ok ? 'PASS' : 'FAIL').' '.$label);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
