<?php

declare(strict_types=1);

test('schedule file includes CloseExpiredCirculationsJob', function () {
    $content = file_get_contents(dirname(__DIR__, 3).'/routes/console.php');
    expect($content)->toContain('CloseExpiredCirculationsJob');
});

test('schedule file includes SendCirculationReminders', function () {
    $content = file_get_contents(dirname(__DIR__, 3).'/routes/console.php');
    expect($content)->toContain('SendCirculationReminders');
});
