<?php

namespace Kiener\MolliePayments\Traits\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ApiTrait
{
    /**
     * @param string $error
     * @return JsonResponse
     */
    protected function buildErrorResponse(string $error): JsonResponse
    {
        $statusCode = 500;

        # we always need a list of 'errors'.
        # otherwise the administration might show things like...nothing found at index 0.
        # this is hardcoded in the admin axios client!

        return new JsonResponse(
            [
                'success' => false,
                'errors' => [
                    $error
                ]
            ],
            $statusCode
        );
    }
}
