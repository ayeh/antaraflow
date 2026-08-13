<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/**
 * VerifyCsrfToken returns early while running tests, so a POST cannot be made
 * to fail on the token. The renderer registered in bootstrap/app.php is asked
 * directly instead — it is the thing that was added, and the thing that decides
 * whether a guest sees a form or Laravel's 419 page.
 */
function renderStaleToken(string $routeName, mixed $parameter, array $input = []): mixed
{
    $request = Request::create(route($routeName, $parameter), 'POST', $input);
    $request->setLaravelSession(app('session.store'));
    $request->setRouteResolver(
        fn () => app('router')->getRoutes()->match(
            Request::create(route($routeName, $parameter), 'POST'),
        ),
    );

    return app(ExceptionHandler::class)->render($request, new HttpException(419));
}

beforeEach(function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $this->meeting = MinutesOfMeeting::createForOrganization($org->id, [
        'title' => 'Board meeting',
        'created_by' => $user->id,
    ]);

    $this->token = $this->meeting->qrRegistrationTokens()->create([
        'token' => 'expiry-token-4001',
        'join_code' => 'EXP001',
        'is_active' => true,
        'required_fields' => ['name'],
        'registrations_count' => 0,
    ]);
});

test('a guest whose form went stale is sent back to it, not shown 419', function () {
    $response = renderStaleToken('qr-registration.submit', $this->token->token, [
        'name' => 'Nurul Aina',
    ]);

    expect($response->getStatusCode())->toBe(302)
        ->and(session('warning'))->not->toBeNull();
});

test('what they typed survives, minus the token', function () {
    renderStaleToken('qr-registration.submit', $this->token->token, [
        'name' => 'Nurul Aina',
        'company' => 'Antara Holdings',
        '_token' => 'stale',
    ]);

    expect(session('_old_input.name'))->toBe('Nurul Aina')
        ->and(session('_old_input.company'))->toBe('Antara Holdings')
        // Handing the dead token back would guarantee the retry fails too.
        ->and(session('_old_input._token'))->toBeNull();
});

test('an authenticated screen still gets the normal 419', function () {
    $request = Request::create('/meetings', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->setRouteResolver(fn () => null);

    $response = app(ExceptionHandler::class)->render($request, new HttpException(419));

    // Not redirected: only the guest-facing routes are given the soft landing.
    expect($response->getStatusCode())->toBe(419);
});

test('a fresh submission still registers the person', function () {
    $this->post(route('qr-registration.submit', $this->token->token), [
        'name' => 'Lim Wei Sheng',
        'email' => 'lim@example.com',
    ])->assertRedirect();

    expect($this->meeting->attendees()->count())->toBe(1);
});
