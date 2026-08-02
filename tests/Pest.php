<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit/Domain');

pest()->extend(Tests\TestCase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A TranscriberFactory that hands back the given transcriber for every mode,
 * so job tests can drive the job with a stubbed provider.
 */
function fakeTranscriberFactory(
    App\Infrastructure\AI\Contracts\TranscriberInterface $transcriber
): App\Infrastructure\AI\TranscriberFactory {
    $factory = Mockery::mock(App\Infrastructure\AI\TranscriberFactory::class);
    $factory->shouldReceive('for')->andReturn($transcriber);

    return $factory;
}

/**
 * Whether ffmpeg and ffprobe are on PATH, for tests that exercise real audio handling.
 */
function ffmpegAvailable(): bool
{
    static $available = null;

    return $available ??= Illuminate\Support\Facades\Process::run(['which', 'ffmpeg'])->successful()
        && Illuminate\Support\Facades\Process::run(['which', 'ffprobe'])->successful();
}
