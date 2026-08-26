<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Plugin\Fake;

use Mollie\Shopware\Component\Installer\DataRemoval\DataRemoverInterface;
use Shopware\Core\Framework\Context;

final class FakeDataRemover implements DataRemoverInterface
{
    private int $removeCount = 0;

    public function remove(Context $context): void
    {
        ++$this->removeCount;
    }

    public function getRemoveCount(): int
    {
        return $this->removeCount;
    }
}
