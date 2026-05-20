<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Payment\ExpressMethod\AbstractCartBackupService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeCartBackupService extends AbstractCartBackupService
{
    private bool $shouldThrow = false;

    public function setShouldThrow(bool $shouldThrow): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function getDecorated(): AbstractCartBackupService
    {
        throw new DecorationPatternException(self::class);
    }

    public function backupCart(SalesChannelContext $context): void
    {
    }

    public function restoreCart(SalesChannelContext $context): Cart
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException('Fake restore error');
        }

        return new Cart('backup-token');
    }

    public function replaceToken(string $oldToken, string $currentToken, SalesChannelContext $context): void
    {
    }

    public function isBackupExisting(SalesChannelContext $context): bool
    {
        return true;
    }

    public function clearBackup(SalesChannelContext $context): void
    {
    }
}
