<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder\Payments;

use DateTime;
use DateTimeZone;
use Faker\Extension\Container;
use Kiener\MolliePayments\Handler\Method\BancomatPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayLaterPayment;
use Kiener\MolliePayments\Handler\Method\TwintPayment;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Api\Types\PaymentMethod;
use MolliePayments\Tests\Fakes\FakeContainer;
use MolliePayments\Tests\Service\MollieApi\Builder\AbstractMollieOrderBuilder;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

class BancomatOrderBuilderTest extends AbstractMollieOrderBuilder
{
    public function testOrderBuild(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);

        $this->paymentHandler = new BancomatPayment(
            $this->loggerService,
            new FakeContainer()
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

        $phoneNumber = '+1234567';
        $orderAttributes = new OrderAttributes($order);
        $orderAttributes->setBancomatPayPhoneNumber($phoneNumber);
        $order->setCustomFields($orderAttributes->toArray());

        $actual = $this->builder->buildOrderPayload($order, $transactionId, $this->paymentHandler::PAYMENT_METHOD_NAME, $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d');

        $expectedBillingAddress = $this->getExpectedTestAddress($this->address, $this->email);
        $expectedBillingAddress['phone'] = $phoneNumber;

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $this->paymentHandler::PAYMENT_METHOD_NAME,
            'orderNumber' => $orderNumber,
            'payment' => ['webhookUrl' => $redirectWebhookUrl],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $expectedBillingAddress,
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }
}
