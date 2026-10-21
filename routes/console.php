<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('imary:notify-chapters')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->hourly();
