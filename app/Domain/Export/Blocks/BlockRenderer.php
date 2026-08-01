<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks;

use PhpOffice\PhpWord\Element\Section;

interface BlockRenderer
{
    public function type(): string;

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string;

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void;
}
