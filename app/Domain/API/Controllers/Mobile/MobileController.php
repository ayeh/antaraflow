<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;

/**
 * Base controller for the mobile API.
 *
 * Unlike the API-key layer in App\Domain\API\Controllers\ApiController, the
 * global OrganizationScope IS active here: requests are authenticated as a real
 * user through the sanctum guard, so queries are already tenant-scoped. Adding
 * a manual `where('organization_id', ...)` on top would be redundant and can
 * mask a scope regression.
 */
abstract class MobileController extends Controller
{
    use AuthorizesRequests;

    protected function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    protected function organizationId(Request $request): int
    {
        return (int) $this->user($request)->current_organization_id;
    }

    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return min(max((int) $request->integer('per_page', $default), 1), $max);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  iterable<int, mixed>  $data
     */
    protected function paginated(LengthAwarePaginator $paginator, iterable $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    protected function failure(string $message, string $code, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
