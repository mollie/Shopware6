<?php

namespace Mollie\PHPUnit\Components\Payment\Mollie;

use Mollie\Api\Http\Data\Money;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Shopware\Component\Payment\Mollie\RequestFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class RequestFactoryTest extends TestCase
{
    public function testCanCreateBasicRequest(): void
    {
        $requestFactory = new RequestFactory();

        $order = new OrderEntity();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $order->setAmountNet('10.0001');
        $order->setCurrency($currency);
        $order->setOrderNumber('123456');

        $actual = $requestFactory->createPayment($order);

        $expected = new CreatePaymentRequest(
            '123456',
            new Money('EUR', '10.00')
        );

        Assert::assertInstanceOf(CreatePaymentRequest::class, $actual);
        Assert::assertEquals($expected, $actual);
    }
}