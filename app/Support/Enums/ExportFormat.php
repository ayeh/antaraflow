<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ExportFormat: string
{
    case Pdf = 'pdf';
    case Docx = 'docx';
    case Json = 'json';
    case Markdown = 'markdown';
    case Csv = 'csv';
}
