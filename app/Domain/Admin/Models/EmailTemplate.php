<?php

declare(strict_types=1);

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->where('is_active', true)->first();
    }

    /** @param array<string, string> $data */
    public function render(array $data): string
    {
        $body = $this->body_html;

        foreach ($data as $key => $value) {
            $body = str_replace('{{'.$key.'}}', e($value), $body);
        }

        return $body;
    }

    /** @param array<string, string> $data */
    public function renderSubject(array $data): string
    {
        $subject = $this->subject;

        foreach ($data as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', $value, $subject);
        }

        return $subject;
    }
}
