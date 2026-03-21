<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns API info without authentication', function (): void {
    $this->getJson('/api/v1')
        ->assertOk()
        ->assertJsonPath('version', 'v1');
});

it('returns API name and description', function (): void {
    $this->getJson('/api/v1')
        ->assertOk()
        ->assertJsonStructure(['name', 'version', 'description', 'endpoints']);
});
