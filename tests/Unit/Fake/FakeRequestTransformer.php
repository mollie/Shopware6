<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Symfony\Component\HttpFoundation\Request;

final class FakeRequestTransformer implements RequestTransformerInterface
{
    /**
     * @param array<string, mixed> $inheritableAttributes the attributes Shopware would carry over
     *                                                    into a sub-request
     */
    public function __construct(private array $inheritableAttributes = [])
    {
    }

    public function transform(Request $request): Request
    {
        return $request;
    }

    public function extractInheritableAttributes(Request $sourceRequest): array
    {
        return $this->inheritableAttributes;
    }
}
