<?php
declare(strict_types=1);

namespace Mollie\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Money;
use Monolog\Test\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

final class MoneyTest extends TestCase
{
    public function testCreateFromTaxFreeOrder(): void
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $orderEntity = new OrderEntity();
        $orderEntity->setCurrency($currency);
        $orderEntity->setTaxStatus(CartPrice::TAX_STATE_FREE);
        $orderEntity->setAmountNet(19.99);
        $orderEntity->setAmountTotal(25.00);
        $money = Money::fromOrder($orderEntity);

        $this->assertSame('19.99', $money->getValue());
        $this->assertSame('EUR', $money->getCurrency());
    }
}
