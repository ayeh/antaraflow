<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\ResellerSetting;
use App\Domain\Account\Services\ResellerService;
use App\Domain\Admin\Services\BrandingService;
use App\Http\Middleware\ResolveSubdomain;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create([
        'current_organization_id' => $this->org->id,
    ]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

it('loads the reseller dashboard for a reseller organization', function () {
    ResellerSetting::factory()->reseller()->create([
        'organization_id' => $this->org->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get('/reseller');

    $response->assertSuccessful();
    $response->assertSee('Reseller Dashboard');
});

it('denies non-reseller access to reseller pages', function () {
    $response = $this->actingAs($this->user)
        ->get('/reseller');

    $response->assertForbidden();
});

it('creates a sub-organization with parent_organization_id set', function () {
    ResellerSetting::factory()->reseller()->create([
        'organization_id' => $this->org->id,
    ]);

    $response = $this->actingAs($this->user)
        ->post('/reseller/sub-organizations', [
            'name' => 'Sub Org Test',
            'description' => 'A test sub-org',
            'owner_name' => 'Sub Owner',
            'owner_email' => 'subowner@example.com',
        ]);

    $response->assertRedirect('/reseller/sub-organizations');

    $subOrg = Organization::query()
        ->where('name', 'Sub Org Test')
        ->first();

    expect($subOrg)->not->toBeNull();
    expect($subOrg->parent_organization_id)->toBe($this->org->id);
});

it('resolves organization from subdomain via middleware', function () {
    $resellerSetting = ResellerSetting::factory()->reseller()->create([
        'organization_id' => $this->org->id,
        'subdomain' => 'acme',
    ]);

    config(['app.domain' => 'antaraflow.test']);

    $request = \Illuminate\Http\Request::create('https://acme.antaraflow.test/login');
    $request->headers->set('HOST', 'acme.antaraflow.test');

    $middleware = app(ResolveSubdomain::class);

    $middleware->handle($request, function ($req) {
        expect($req->attributes->get('reseller_organization'))->not->toBeNull();
        expect($req->attributes->get('reseller_organization')->id)->toBe($this->org->id);
        expect($req->attributes->get('reseller_setting')->subdomain)->toBe('acme');

        return response('ok');
    });
});

it('resolves organization from custom domain via middleware', function () {
    $resellerSetting = ResellerSetting::factory()->reseller()->withCustomDomain()->create([
        'organization_id' => $this->org->id,
        'custom_domain' => 'meetings.acmecorp.com',
    ]);

    $request = \Illuminate\Http\Request::create('https://meetings.acmecorp.com/login');
    $request->headers->set('HOST', 'meetings.acmecorp.com');

    $middleware = app(ResolveSubdomain::class);

    $middleware->handle($request, function ($req) {
        expect($req->attributes->get('reseller_organization'))->not->toBeNull();
        expect($req->attributes->get('reseller_organization')->id)->toBe($this->org->id);

        return response('ok');
    });
});

it('applies branding overrides via subdomain', function () {
    ResellerSetting::factory()->reseller()->create([
        'organization_id' => $this->org->id,
        'subdomain' => 'branded',
        'branding_overrides' => [
            'app_name' => 'ACME Meetings',
            'primary_color' => '#ff0000',
        ],
    ]);

    config(['app.domain' => 'antaraflow.test']);

    $request = \Illuminate\Http\Request::create('https://branded.antaraflow.test/login');
    $request->headers->set('HOST', 'branded.antaraflow.test');

    $middleware = app(ResolveSubdomain::class);

    $middleware->handle($request, function ($req) {
        $branding = app(BrandingService::class);

        expect($branding->get('app_name'))->toBe('ACME Meetings');
        expect($branding->get('primary_color'))->toBe('#ff0000');

        return response('ok');
    });
});

it('respects max sub-organization limit', function () {
    ResellerSetting::factory()->reseller()->create([
        'organization_id' => $this->org->id,
        'max_sub_organizations' => 1,
    ]);

    $this->actingAs($this->user);

    $service = app(ResellerService::class);

    $service->createSubOrganization($this->org, [
        'name' => 'First Sub Org',
        'owner_name' => 'Owner One',
        'owner_email' => 'owner1@example.com',
    ]);

    expect(fn () => $service->createSubOrganization($this->org, [
        'name' => 'Second Sub Org',
        'owner_name' => 'Owner Two',
        'owner_email' => 'owner2@example.com',
    ]))->toThrow(\RuntimeException::class, 'Maximum sub-organization limit reached.');
});

it('shows organization branding on login page via subdomain', function () {
    ResellerSetting::factory()->reseller()->create([
        'organization_id' => $this->org->id,
        'subdomain' => 'customlogin',
        'branding_overrides' => [
            'app_name' => 'Custom Portal',
        ],
    ]);

    config(['app.domain' => 'antaraflow.test']);

    $response = $this->get('http://customlogin.antaraflow.test/login');

    $response->assertSuccessful();
    $response->assertSee('Custom Portal');
    $response->assertSee('Powered by');
});
