<?php

declare(strict_types=1);

test('mom-confirm view includes print styles', function () {
    $viewPath = dirname(__DIR__, 3).'/resources/views/meetings/mom-confirm.blade.php';
    $content = file_get_contents($viewPath);
    expect($content)->toContain('@media print');
});
