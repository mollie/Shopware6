<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Traits\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ApiTrait
{
    protected function buildErrorResponse(string $error): JsonResponse
    {
        $statusCode = 500;

        // we always need a list of 'errors'.
        // otherwise the administration might show things like...nothing found at index 0.
        // this is hardcoded in the admin axios client!

        return new JsonResponse(
            [
                'success' => false,
                'errors' => [
                    $error,
                ],
            ],
            $statusCode
        );
    }
}
