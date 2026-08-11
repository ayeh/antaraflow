<?php

declare(strict_types=1);

namespace App\Domain\API\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Gives every mobile endpoint the same error envelope.
 *
 * The app decides what to do from `code` and shows `message` to the person
 * holding the phone, so the message is always translated and the code is always
 * present — including for framework-thrown failures nobody wrote by hand.
 */
class MobileExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/mobile/*')) {
                return null;
            }

            return self::render($e);
        });
    }

    private static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => response()->json([
                'message' => __('The information provided is not valid.'),
                'code' => 'VALIDATION_FAILED',
                'errors' => $e->errors(),
            ], 422),

            $e instanceof AuthenticationException => response()->json([
                'message' => __('Please sign in again.'),
                'code' => 'UNAUTHENTICATED',
            ], 401),

            // Laravel converts AuthorizationException to AccessDeniedHttpException
            // before render callbacks run, so both forms have to be caught here
            // and ahead of the general HttpExceptionInterface branch below.
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => response()->json([
                'message' => self::isDefaultDenial($e->getMessage())
                    ? __('You are not allowed to do that.')
                    : $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403),

            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => response()->json([
                'message' => __('Not found.'),
                'code' => 'NOT_FOUND',
            ], 404),

            $e instanceof HttpExceptionInterface => response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : __('Request could not be completed.'),
                'code' => self::codeForStatus($e->getStatusCode()),
            ], $e->getStatusCode()),

            default => response()->json([
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : __('Something went wrong. Please try again.'),
                'code' => 'SERVER_ERROR',
            ], 500),
        };
    }

    private static function isDefaultDenial(string $message): bool
    {
        return $message === '' || $message === 'This action is unauthorized.';
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            401 => 'UNAUTHENTICATED',
            402 => 'QUOTA_EXCEEDED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            410 => 'GONE',
            422 => 'VALIDATION_FAILED',
            426 => 'CLIENT_UPGRADE_REQUIRED',
            429 => 'RATE_LIMITED',
            default => 'REQUEST_FAILED',
        };
    }
}
