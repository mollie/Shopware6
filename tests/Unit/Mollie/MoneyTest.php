<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Money;
use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(Money::class)]
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
        $money = Money::fromOrder($orderEntity,$currency);

        $this->assertSame(19.99, $money->getValue());
        $this->assertSame('EUR', $money->getCurrency());
    }

    public function testFromArrayReadsValueAndCurrency(): void
    {
        $money = Money::fromArray(['value' => '19.99', 'currency' => 'EUR']);

        $this->assertSame(19.99, $money->getValue());
        $this->assertSame('EUR', $money->getCurrency());
    }

    public function testFromArrayFallsBackToZeroAndFallbackCurrency(): void
    {
        $money = Money::fromArray([], 'USD');

        $this->assertSame(0.0, $money->getValue());
        $this->assertSame('USD', $money->getCurrency());
    }

    public function testFromArrayPrefersOwnCurrencyOverFallback(): void
    {
        $money = Money::fromArray(['value' => '4.00', 'currency' => 'EUR'], 'USD');

        $this->assertSame(4.0, $money->getValue());
        $this->assertSame('EUR', $money->getCurrency());
    }

    public function testDefaultCurrencyUsesTwoDecimals(): void
    {
        $money = new Money(10.5, 'EUR');

        $this->assertSame(2, $money->getDecimals());
        $this->assertSame(['value' => '10.50', 'currency' => 'EUR'], $money->jsonSerialize());
    }

    public function testZeroDecimalCurrencyHasNoDecimals(): void
    {
        $money = new Money(5000.0, 'JPY');

        $this->assertSame(0, $money->getDecimals());
        $this->assertSame(['value' => '5000', 'currency' => 'JPY'], $money->jsonSerialize());
    }
}
