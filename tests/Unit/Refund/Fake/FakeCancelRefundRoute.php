<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Fake;

use Mollie\Shopware\Component\Refund\Route\AbstractCancelRefundRoute;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * See FakeCreateRefundRoute.
 */
final class FakeCancelRefundRoute extends AbstractCancelRefundRoute
{
    /** @var list<array{payload: array<string, mixed>, versionId: string}> */
    public array $calls = [];

    public function __construct(private ?\Throwable $failWith = null)
    {
    }

    public function getDecorated(): AbstractCancelRefundRoute
    {
        throw new \RuntimeException('not decorated');
    }

    public function cancel(Request $request, Context $context): JsonResponse
    {
        $this->calls[] = [
            'payload' => $request->request->all(),
            'versionId' => $context->getVersionId(),
        ];

        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return new JsonResponse([]);
    }
}
