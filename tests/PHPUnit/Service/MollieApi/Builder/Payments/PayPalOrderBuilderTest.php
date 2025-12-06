<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\MollieApi\Builder\Payments;

use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Mollie\Api\Types\PaymentMethod;
use MolliePayments\Shopware\Tests\Service\MollieApi\Builder\AbstractMollieOrderBuilder;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

class PayPalOrderBuilderTest extends AbstractMollieOrderBuilder
{
    public function testOrderBuild(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::PAYPAL;

        $this->paymentHandler = new PayPalPayment(
            $this->payAction,
            $this->finalizeAction
        );

        $transactionId = Uuid::randomHex();
        $amountTotal = 27.0;
        $taxStatus = CartPrice::TAX_STATE_GROSS;
        $currencyISO = 'EUR';

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($currencyISO);

        $orderNumber = 'foo number';
        $lineItems = $this->getDummyLineItems();

        $order = $this->getOrderEntity($amountTotal, $taxStatus, $currencyISO, $lineItems, $orderNumber);

        $actual = $this->builder->buildOrderPayload($order, $transactionId, $paymentMethod, $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d')
        ;

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $paymentMethod,
            'orderNumber' => $orderNumber,
            'payment' => ['webhookUrl' => $redirectWebhookUrl],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime,
        ];

        self::assertSame($expected, $actual);
    }

    public function testOrderBuildWithDelivery(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::PAYPAL;

        $this->paymentHandler = new PayPalPayment(
            $this->payAction,
            $this->finalizeAction
        );

        $transactionId = Uuid::randomHex();
        $amountTotal = 42.0;
        $taxStatus = CartPrice::TAX_STATE_GROSS;
        $currencyISO = 'EUR';

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($currencyISO);

        $orderNumber = 'foo number';
        $lineItems = $this->getDummyLineItems();

        $order = $this->getOrderEntity($amountTotal, $taxStatus, $currencyISO, $lineItems, $orderNumber);

        $taxAmount = 15.0;
        $taxRate = 50.0;
        $totalPrice = 15.0;
        $deliveryId = Uuid::randomHex();

        $delivery = $this->getOrderDelivery($deliveryId, $taxAmount, $taxRate, $totalPrice);
        $delivery->setShippingOrderAddress($this->getDummyAddress());
        $deliveries = new OrderDeliveryCollection([$delivery]);

        $order->setDeliveries($deliveries);

        $actual = $this->builder->buildOrderPayload($order, $transactionId, $paymentMethod, $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d')
        ;

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $paymentMethod,
            'orderNumber' => $orderNumber,
            'payment' => ['webhookUrl' => $redirectWebhookUrl],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => array_merge(
                $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
                $this->getExpectedDeliveries($taxStatus, $deliveries, $currency)
            ),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime,
        ];

        self::assertSame($expected, $actual);
    }
}
