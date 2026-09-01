<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Return\Fake;

use Mollie\Shopware\Component\Refund\Return\OrderReturnLoaderInterface;
use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnStruct;
use Shopware\Core\Framework\Context;

/**
 * Stands in for the mapper that reads the SwagCommercial return. The real one cannot run in a unit
 * test because SwagCommercial is not part of the plugin's vendor/.
 */
final class FakeOrderReturnLoader implements OrderReturnLoaderInterface
{
    /** @var list<array{returnId: string, versionId: string}> */
    public array $loadCalls = [];

    public function __construct(
        private ?OrderReturnStruct $orderReturn = null,
        private bool $available = true,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function load(string $returnId, Context $context): ?OrderReturnStruct
    {
        $this->loadCalls[] = ['returnId' => $returnId, 'versionId' => $context->getVersionId()];

        return $this->orderReturn;
    }
}
