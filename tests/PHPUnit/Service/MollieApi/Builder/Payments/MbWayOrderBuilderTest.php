<?php declare(strict_types=1);

namespace PHPUnit\Service\MollieApi\Builder\Payments;

use DateTime;
use DateTimeZone;
use Kiener\MolliePayments\Handler\Method\MbWayPayment;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use MolliePayments\Tests\Fakes\FakeContainer;
use MolliePayments\Tests\Service\MollieApi\Builder\AbstractMollieOrderBuilder;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

class MbWayOrderBuilderTest extends AbstractMollieOrderBuilder
{
    public function testOrderBuild(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);

        $this->paymentHandler = new MbWayPayment(
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

        $actual = $this->builder->buildOrderPayload($order, $transactionId, $this->paymentHandler::PAYMENT_METHOD_NAME, $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d');

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $this->paymentHandler::PAYMENT_METHOD_NAME,
            'orderNumber' => $orderNumber,
            'payment' => ['webhookUrl' => $redirectWebhookUrl],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }
}
