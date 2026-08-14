<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('admin can view users index', function () {
    User::factory()->count(3)->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertStatus(200)
        ->assertSee('Users');
});

test('admin can search users', function () {
    // Emails are pinned, not left to the factory. The search matches email as
    // well as name, and the factory fills email from `fake()->safeEmail()`,
    // which is built out of a random person's name — so roughly one run in two
    // hundred handed the row named "Jane Smith" an address like
    // johnson.eva@example.org, which matches a search for John and put her on
    // the page the assertion below says she must not be on.
    //
    // The test then failed once in a blue moon, on a suite that had not
    // changed, which is the most expensive kind of failure to read.
    User::factory()->create(['name' => 'John Doe', 'email' => 'jdoe@example.test']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jsmith@example.test']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', ['search' => 'John']))
        ->assertStatus(200)
        ->assertSee('John Doe')
        ->assertDontSee('Jane Smith');
});

// The behaviour that made the fixture above fragile, and which nothing covered.
test('the search matches an email as well as a name', function () {
    User::factory()->create(['name' => 'Aminah Yusof', 'email' => 'treasurer@example.test']);
    User::factory()->create(['name' => 'Ravi Kumar', 'email' => 'secretary@example.test']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', ['search' => 'treasurer']))
        ->assertStatus(200)
        ->assertSee('Aminah Yusof')
        ->assertDontSee('Ravi Kumar');
});

test('admin can view user detail', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.show', $user))
        ->assertStatus(200)
        ->assertSee($user->name);
});

test('admin can suspend a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.suspend', $user))
        ->assertRedirect(route('admin.users.show', $user));

    expect($user->fresh()->trashed())->toBeTrue();
});

test('admin can unsuspend a user', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.unsuspend', $user))
        ->assertRedirect(route('admin.users.show', $user));

    expect($user->fresh()->trashed())->toBeFalse();
});

test('admin can impersonate a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.impersonate', $user))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user, 'web');
});

test('admin can export users csv', function () {
    User::factory()->count(3)->create();

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.export'));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('suspended users appear in index with suspended badge', function () {
    $activeUser = User::factory()->create(['name' => 'Active User']);
    $suspendedUser = User::factory()->create(['name' => 'Suspended User']);
    $suspendedUser->delete();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertStatus(200)
        ->assertSee('Active User')
        ->assertSee('Suspended User')
        ->assertSee('Suspended');
});

test('unauthenticated user cannot access users', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('admin.login'));
});

test('the impersonate confirm message escapes a breakout payload in the user name', function () {
    /**
     * The impersonate form's confirm message interpolated $user->name raw into
     * an inline onsubmit handler. A user controls their own display name, so a
     * crafted one broke out of the JS string literal and ran in the SUPERADMIN
     * session the moment the admin clicked Impersonate. @js() escapes the
     * apostrophe to ', which the JS parser reads as a character and the
     * HTML parser leaves alone -- unlike {{ }}'s &#039;, which the browser
     * decodes back to an apostrophe before Alpine/JS ever sees it.
     */
    $payload = "x'); window.__pwned2 = 1; ('";
    $user = User::factory()->create(['name' => $payload]);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.show', $user))
        ->assertStatus(200);

    // Anchored to the message context ("as ... Continue?"), unique to the
    // onsubmit handler -- the name also renders, correctly HTML-escaped,
    // elsewhere on the page. The JS-safe \u0027 is what ships; the &#039; the
    // browser would decode back into a live apostrophe must not reach the sink.
    $response->assertSee('as x\u0027); window.__pwned2 = 1; (\u0027. Continue?', false)
        ->assertDontSee('as x&#039;); window.__pwned2 = 1; (&#039;. Continue?', false);
});
