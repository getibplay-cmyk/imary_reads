<?php

// Used by `composer setup` after dependencies are installed.
require __DIR__.'/../vendor/autoload.php';
use Symfony\Component\Process\Process;

chdir(__DIR__.'/..');
if (! file_exists('.env')) {
    copy('.env.example', '.env');
}
$environment = file_get_contents('.env');
if (! preg_match('/^APP_KEY=(.*)$/m', $environment, $matches) || trim($matches[1], " \t\r\"'") === '') {
    (new Process([PHP_BINARY, 'artisan', 'key:generate', '--no-interaction']))->mustRun(fn ($type, $buffer) => print ($buffer));
}
if (preg_match('/^DB_CONNECTION=sqlite$/m', $environment) && ! file_exists('database/database.sqlite')) {
    touch('database/database.sqlite');
}
(new Process([PHP_BINARY, 'artisan', 'migrate', '--seed', '--force', '--no-interaction']))->mustRun(fn ($type, $buffer) => print ($buffer));
