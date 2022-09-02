<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder\Payments;

use DateTime;
use DateTimeZone;
use Faker\Extension\Container;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Mollie\Api\Types\PaymentMethod;
use MolliePayments\Tests\Fakes\FakeContainer;
use MolliePayments\Tests\Service\MollieApi\Builder\AbstractMollieOrderBuilder;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

class BankTransferOrderBuilderTest extends AbstractMollieOrderBuilder
{
    public function testOrderBuild(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::BANKTRANSFER;


        $this->paymentHandler = new BankTransferPayment(
            $this->loggerService,
            new FakeContainer(),
            $this->settingsService
        );


        $bankDueDays = $this->expiresAt + 5;
        $this->settingStruct->assign([
            'orderLifetimeDays' => $this->expiresAt,
            'paymentMethodBankTransferDueDateDays' => $bankDueDays
        ]);

        $transactionId = Uuid::randomHex();
        $amountTotal = 27.0;
        $taxStatus = CartPrice::TAX_STATE_GROSS;
        $currencyISO = 'EUR';

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($currencyISO);

        $orderNumber = 'foo number';
        $lineItems = $this->getDummyLineItems();

        $order = $this->getOrderEntity($amountTotal, $taxStatus, $currency, $lineItems, $orderNumber);

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $bankDueDays))
            ->format('Y-m-d');

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
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }
}
