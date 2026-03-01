<?php

declare(strict_types=1);

use App\Support\Enums\ExportFormat;

test('export format has expected cases', function () {
    expect(ExportFormat::cases())->toHaveCount(5);
    expect(ExportFormat::Pdf->value)->toBe('pdf');
    expect(ExportFormat::Docx->value)->toBe('docx');
    expect(ExportFormat::Json->value)->toBe('json');
    expect(ExportFormat::Markdown->value)->toBe('markdown');
    expect(ExportFormat::Csv->value)->toBe('csv');
});
