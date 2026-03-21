<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Models\ExportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user->id, ['role' => 'admin']);
});

it('can list export templates', function (): void {
    ExportTemplate::factory()->create(['organization_id' => $this->org->id, 'name' => 'My Template']);

    $this->actingAs($this->user)
        ->get(route('settings.export-templates.index'))
        ->assertOk()
        ->assertSee('My Template');
});

it('can create an export template', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.export-templates.store'), [
            'name' => 'Corporate Template',
            'primary_color' => '#003366',
            'is_default' => true,
        ])
        ->assertRedirect();

    expect(ExportTemplate::where('name', 'Corporate Template')->exists())->toBeTrue();
});

it('sets all other templates to non-default when new default created', function (): void {
    $old = ExportTemplate::factory()->create(['organization_id' => $this->org->id, 'is_default' => true]);

    $this->actingAs($this->user)
        ->post(route('settings.export-templates.store'), [
            'name' => 'New Default',
            'is_default' => true,
        ])
        ->assertRedirect();

    expect($old->fresh()->is_default)->toBeFalse();
});
