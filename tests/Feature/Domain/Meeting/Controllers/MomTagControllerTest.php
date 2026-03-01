<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MomTag;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('org member can view tags index', function () {
    $tag = MomTag::factory()->for($this->org)->create(['name' => 'Strategy']);
    $otherTag = MomTag::factory()->create(['name' => 'OtherOrgTag']); // different org

    $response = $this->actingAs($this->user)->get(route('tags.index'));

    $response->assertOk();
    $response->assertSee($tag->name);
    $response->assertDontSee($otherTag->name);
});

test('org admin can create tag', function () {
    $response = $this->actingAs($this->user)->post(route('tags.store'), [
        'name' => 'Strategy',
        'color' => '#A855F7',
    ]);

    $response->assertRedirect(route('tags.index'));
    $this->assertDatabaseHas('mom_tags', [
        'name' => 'Strategy',
        'organization_id' => $this->org->id,
    ]);
});

test('tag name must be unique within organization', function () {
    MomTag::factory()->for($this->org)->create(['name' => 'Strategy', 'slug' => 'strategy']);

    $response = $this->actingAs($this->user)->post(route('tags.store'), [
        'name' => 'Strategy',
        'color' => '#A855F7',
    ]);

    $response->assertSessionHasErrors('name');
});

test('same tag name can exist in different organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($otherUser, ['role' => UserRole::Owner->value]);

    MomTag::factory()->for($this->org)->create(['name' => 'Strategy', 'slug' => 'strategy']);

    $response = $this->actingAs($otherUser)->post(route('tags.store'), [
        'name' => 'Strategy',
        'color' => '#A855F7',
    ]);

    $response->assertRedirect(route('tags.index'));
    $this->assertDatabaseCount('mom_tags', 2);
});

test('org admin can update tag', function () {
    $tag = MomTag::factory()->for($this->org)->create();

    $response = $this->actingAs($this->user)->put(route('tags.update', $tag), [
        'name' => 'Updated Name',
        'color' => '#3B82F6',
    ]);

    $response->assertRedirect(route('tags.index'));
    $this->assertDatabaseHas('mom_tags', [
        'id' => $tag->id,
        'name' => 'Updated Name',
    ]);
});

test('org admin can delete tag', function () {
    $tag = MomTag::factory()->for($this->org)->create();

    $response = $this->actingAs($this->user)->delete(route('tags.destroy', $tag));

    $response->assertRedirect(route('tags.index'));
    $this->assertDatabaseMissing('mom_tags', ['id' => $tag->id]);
});

test('viewer cannot create tag', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $response = $this->actingAs($viewer)->post(route('tags.store'), [
        'name' => 'Strategy',
        'color' => '#A855F7',
    ]);

    $response->assertForbidden();
});

test('invalid color is rejected', function () {
    $response = $this->actingAs($this->user)->post(route('tags.store'), [
        'name' => 'Strategy',
        'color' => 'not-a-hex',
    ]);

    $response->assertSessionHasErrors('color');
});
