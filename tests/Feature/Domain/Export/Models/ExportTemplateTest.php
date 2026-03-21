<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Models\ExportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create an export template', function (): void {
    $org = Organization::factory()->create();

    $template = ExportTemplate::create([
        'organization_id' => $org->id,
        'name' => 'Corporate Template',
        'is_default' => true,
    ]);

    $template->refresh();

    expect($template->name)->toBe('Corporate Template');
    expect($template->is_default)->toBeTrue();
});
