<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('files:delete-expired')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('outbox:publish')
    ->everyTenSeconds()
    ->withoutOverlapping(5);
