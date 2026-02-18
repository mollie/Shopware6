<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Behat\Context\Context;
use Mollie\Shopware\Integration\Data\SalesChannelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class ShopwareContext implements Context
{
    use SalesChannelTestBehaviour;
    private static ?SalesChannelContext $salesChannelContext = null;
    private static array $options = [];

    public function getCurrentSalesChannelContext(): SalesChannelContext
    {
        if (self::$salesChannelContext === null) {
            self::$salesChannelContext = $this->getDefaultSalesChannelContext('',self::$options);
        }

        return self::$salesChannelContext;
    }

    public function setOptions(string $key, $value): void
    {
        self::$salesChannelContext = null;
        if ($value === null) {
            unset(self::$options[$key]);

            return;
        }
        self::$options[$key] = $value;
    }

    public function getOptions(): array
    {
        return self::$options;
    }
}
