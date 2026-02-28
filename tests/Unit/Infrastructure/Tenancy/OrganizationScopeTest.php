<?php

declare(strict_types=1);

use App\Infrastructure\Tenancy\OrganizationScope;
use App\Infrastructure\Tenancy\SetOrganizationContext;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('OrganizationScope implements Scope interface', function () {
    $scope = new OrganizationScope;

    expect($scope)->toBeInstanceOf(Scope::class);
});

test('OrganizationScope has apply method with correct signature', function () {
    $scope = new OrganizationScope;
    $reflection = new ReflectionMethod($scope, 'apply');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('void');

    $params = $reflection->getParameters();
    expect($params[0]->getType()->getName())->toBe('Illuminate\Database\Eloquent\Builder');
    expect($params[1]->getType()->getName())->toBe('Illuminate\Database\Eloquent\Model');
});

test('BelongsToOrganization trait provides organization relationship method', function () {
    $model = new class extends Model
    {
        use BelongsToOrganization;

        protected $table = 'test_models';
    };

    expect(method_exists($model, 'organization'))->toBeTrue();

    $reflection = new ReflectionMethod($model, 'organization');

    expect($reflection->getReturnType()->getName())->toBe(BelongsTo::class);
});

test('BelongsToOrganization trait has boot method that registers global scope', function () {
    expect(method_exists(BelongsToOrganization::class, 'bootBelongsToOrganization'))->toBeTrue();

    $reflection = new ReflectionMethod(BelongsToOrganization::class, 'bootBelongsToOrganization');

    expect($reflection->isStatic())->toBeTrue();
    expect($reflection->getReturnType()->getName())->toBe('void');
});

test('SetOrganizationContext middleware returns a response', function () {
    $middleware = new SetOrganizationContext;

    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('OK');
});

test('SetOrganizationContext middleware passes through when no user', function () {
    $middleware = new SetOrganizationContext;

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => null);

    $response = $middleware->handle($request, function () {
        return new Response('passed');
    });

    expect($response->getContent())->toBe('passed');
});

test('SetOrganizationContext middleware sets organization when user has none', function () {
    $middleware = new SetOrganizationContext;

    $org = Mockery::mock();
    $org->id = 7;

    $orgQuery = Mockery::mock();
    $orgQuery->shouldReceive('first')->andReturn($org);

    $user = Mockery::mock();
    $user->current_organization_id = null;
    $user->shouldReceive('getAttribute')->with('current_organization_id')->andReturn(null);
    $user->shouldReceive('organizations')->andReturn($orgQuery);
    $user->shouldReceive('update')->once()->with(['current_organization_id' => 7]);

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('SetOrganizationContext middleware has correct handle signature', function () {
    $middleware = new SetOrganizationContext;
    $reflection = new ReflectionMethod($middleware, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('Symfony\Component\HttpFoundation\Response');
});
