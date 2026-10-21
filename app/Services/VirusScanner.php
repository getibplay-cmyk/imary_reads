<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class VirusScanner
{
    public function clean(string $path): bool
    {
        // Argument arrays avoid shell interpolation. Missing/failed scanner fails closed.
        $process = new Process([config('imary.scanner'), '--no-summary', '--', $path]);
        $process->setTimeout(45);
        $process->run();
        if ($process->getExitCode() === 1) {
            return false;
        }
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Scanner unavailable. File remains quarantined.');
        }

        return true;
    }
}
