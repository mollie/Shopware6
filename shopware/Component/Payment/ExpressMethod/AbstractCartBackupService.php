<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractCartBackupService
{
    abstract public function getDecorated(): self;

    abstract public function backupCart(SalesChannelContext $context): void;

    abstract public function restoreCart(SalesChannelContext $context): Cart;

    abstract public function replaceToken(string $oldToken, string $currentToken, SalesChannelContext $context): void;

    abstract public function isBackupExisting(SalesChannelContext $context): bool;

    abstract public function clearBackup(SalesChannelContext $context): void;
}
