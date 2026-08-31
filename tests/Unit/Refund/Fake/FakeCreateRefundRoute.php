<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Fake;

use Mollie\Shopware\Component\Refund\Route\AbstractCreateRefundRoute;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Records the refund request instead of sending it. The parent only carries the abstract signature
 * and the controller machinery, so nothing has to be constructed.
 */
final class FakeCreateRefundRoute extends AbstractCreateRefundRoute
{
    /** @var list<array{payload: array<string, mixed>, versionId: string}> */
    public array $calls = [];

    public function __construct(private ?\Throwable $failWith = null)
    {
    }

    public function getDecorated(): AbstractCreateRefundRoute
    {
        throw new \RuntimeException('not decorated');
    }

    public function create(Request $request, Context $context): JsonResponse
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
