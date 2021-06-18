<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use DateTimeZone;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\DirectDebitPayment;
use Kiener\MolliePayments\Handler\Method\EpsPayment;
use Kiener\MolliePayments\Handler\Method\GiftCardPayment;
use Kiener\MolliePayments\Handler\Method\GiroPayPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Handler\Method\KbcPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayLaterPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaSliceItPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\PaySafeCardPayment;
use Kiener\MolliePayments\Handler\Method\Przelewy24Payment;
use Kiener\MolliePayments\Handler\Method\SofortPayment;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderAddressBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\MollieOrderCustomerEnricher;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use Mollie\Api\Types\PaymentMethod;
use MolliePayments\Tests\Traits\OrderTrait;
use MolliePayments\Tests\Utils\Traits\PaymentBuilderTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

class MollieOrderBuilderTest extends TestCase
{
    use OrderTrait;
    use PaymentBuilderTrait;

    /**
     * @var SettingsService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $settingsService;
    /**
     * @var LoggerService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerService;
    /**
     * @var OrderDataExtractor|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderDataExtractor;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RouterInterface
     */
    private $router;
    /**
     * @var MollieOrderBuilder
     */
    private $builder;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|SalesChannelContext
     */
    private $salesChannelContext;
    /**
     * @var PaymentHandler
     */
    private $paymentHandler;
    /**
     * @var CustomerEntity
     */
    private $customer;
    /**
     * @var CustomerAddressEntity
     */
    private $address;
    /**
     * @var string
     */
    private $email;
    /**
     * @var int
     */
    private $expiresAt;
    /**
     * @var string
     */
    private $localeCode;
    /**
     * @var MolliePaymentDoPay|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mollieDoPaymentFacade;
    /**
     * @var MolliePaymentFinalize|\PHPUnit\Framework\MockObject\MockObject
     */
    private $molliePaymentFinalize;
    /**
     * @var TransactionTransitionServiceInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $transitionService;
    /**
     * @var MollieSettingStruct
     */
    private $settingStruct;

    public function setUp(): void
    {
        $this->address = $this->getDummyAddress();
        $this->email = 'foo@bar.de';
        $this->expiresAt = 12;

        $this->address = $this->getDummyAddress();
        $this->customer = $this->getDummyCustomer($this->address, $this->address, $this->email);

        $this->localeCode = 'de_DE';
        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode($this->localeCode);

        /** @var OrderDataExtractor customerService */
        $this->orderDataExtractor = $this->getMockBuilder(OrderDataExtractor::class)->disableOriginalConstructor()->getMock();
        $this->orderDataExtractor->method('extractCustomer')->willReturn($this->customer);
        $this->orderDataExtractor->method('extractLocale')->willReturn($locale);

        /** @var SettingsService settingsService */
        $this->settingsService = $this->getMockBuilder(SettingsService::class)->disableOriginalConstructor()->getMock();
        $this->settingStruct = new MollieSettingStruct();
        $this->settingStruct->assign(['orderLifetimeDays' => $this->expiresAt]);
        $this->settingsService->method('getSettings')->willReturn($this->settingStruct);

        /** @var LoggerService loggerService */
        $this->loggerService = $this->getMockBuilder(LoggerService::class)->disableOriginalConstructor()->getMock();
        /** @var SalesChannelContext salesChannelContext */
        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        /** @var Router router */
        $this->router = $this->getMockBuilder(RouterInterface::class)->disableOriginalConstructor()->getMock();
        /** @var MolliePaymentDoPay $mollieDoPaymentFacade */
        $this->mollieDoPaymentFacade = $this->getMockBuilder(MolliePaymentDoPay::class)->disableOriginalConstructor()->getMock();
        /** @var MolliePaymentFinalize $molliePaymentFianlize */
        $this->molliePaymentFinalize = $this->getMockBuilder(MolliePaymentFinalize::class)->disableOriginalConstructor()->getMock();
        /** @var TransactionTransitionServiceInterface $transitionService */
        $this->transitionService = $this->getMockBuilder(TransactionTransitionServiceInterface::class)->disableOriginalConstructor()->getMock();

        $this->builder = new MollieOrderBuilder(
            $this->settingsService,
            $this->orderDataExtractor,
            $this->router,
            new MollieOrderPriceBuilder(),
            new MollieLineItemBuilder(new MollieOrderPriceBuilder(), new IsOrderLineItemValid(), new PriceCalculator(), new LineItemDataExtractor()),
            new MollieOrderAddressBuilder(),
            new MollieOrderCustomerEnricher(),
            $this->loggerService
        );
    }

    public function testApplePayOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::APPLEPAY;
        $this->paymentHandler = new ApplePayPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService, $this->settingsService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testBanContactOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::BANCONTACT;
        $this->paymentHandler = new BanContactPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testBankTransferOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::BANKTRANSFER;
        $this->paymentHandler = new BankTransferPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService, $this->settingsService);
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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
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

    public function testBelfiusOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::BELFIUS;
        $this->paymentHandler = new BelfiusPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testCreditCardOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::CREDITCARD;
        /** @var CustomerService $customerService */
        $customerService = $this->getMockBuilder(CustomerService::class)->disableOriginalConstructor()->getMock();
        $customerService->expects($this->never())->method('setCardToken');
        $this->paymentHandler = new CreditCardPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService, $customerService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testCreditCardOrderWithToken(): void
    {
        $token = 'secrettoken';
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::CREDITCARD;
        /** @var CustomerService $customerService */
        $customerService = $this->getMockBuilder(CustomerService::class)->disableOriginalConstructor()->getMock();
        $customerService->expects($this->once())->method('setCardToken');
        $this->paymentHandler = new CreditCardPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService, $customerService);
        $this->customer->setCustomFields(['mollie_payments' => ['credit_card_token' => $token]]);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d');

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $paymentMethod,
            'orderNumber' => $orderNumber,
            'payment' => [
                'webhookUrl' => $redirectWebhookUrl,
                'cardToken' => $token
            ],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }

    public function testDirectDebitOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::DIRECTDEBIT;
        $this->paymentHandler = new DirectDebitPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);
        $firstName = 'First';
        $lastName = 'Last';
        $this->customer->setFirstName($firstName);
        $this->customer->setLastName($lastName);
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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d');

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $paymentMethod,
            'orderNumber' => $orderNumber,
            'payment' => [
                'webhookUrl' => $redirectWebhookUrl,
                'consumerName' => sprintf('%s %s', $firstName, $lastName)
            ],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }

    public function testEpsOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::EPS;
        $this->paymentHandler = new EpsPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testGiftCardOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::GIFTCARD;
        $this->paymentHandler = new GiftCardPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testGiroPayOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::GIROPAY;
        $this->paymentHandler = new GiroPayPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testIdealOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::IDEAL;
        $this->paymentHandler = new iDealPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);
        $preferredIdealIssuer = 'preferredIssuer';
        $this->customer->setCustomFields([
            'mollie_payments' => ['preferred_ideal_issuer' => $preferredIdealIssuer]
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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d');

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $paymentMethod,
            'orderNumber' => $orderNumber,
            'payment' => [
                'webhookUrl' => $redirectWebhookUrl,
                'issuer' => $preferredIdealIssuer
            ],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }

    public function testKbcOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::KBC;
        $this->paymentHandler = new KbcPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

        $transactionId = Uuid::randomHex();
        $amountTotal = 27.0;
        $taxStatus = CartPrice::TAX_STATE_GROSS;
        $currencyISO = 'EUR';

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($currencyISO);

        $orderNumber = 'foo number very long';
        $lineItems = $this->getDummyLineItems();

        $order = $this->getOrderEntity($amountTotal, $taxStatus, $currency, $lineItems, $orderNumber);

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d');

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $paymentMethod,
            'orderNumber' => $orderNumber,
            'payment' => [
                'webhookUrl' => $redirectWebhookUrl,
                'description' => substr($orderNumber, -(KbcPayment::KBC_DESCRIPTION_LENGTH))
            ],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }

    public function testPayLaterOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::KLARNA_PAY_LATER;
        $this->paymentHandler = new KlarnaPayLaterPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testSliceItOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::KLARNA_SLICE_IT;
        $this->paymentHandler = new KlarnaSliceItPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testPayPalOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::PAYPAL;
        $this->paymentHandler = new PayPalPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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

    public function testPaySafeCardOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::PAYSAFECARD;
        $this->paymentHandler = new PaySafeCardPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);
        $customerNumber = 'fooBar';
        $this->customer->setCustomerNumber($customerNumber);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d');

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $paymentMethod,
            'orderNumber' => $orderNumber,
            'payment' => [
                'webhookUrl' => $redirectWebhookUrl,
                'customerReference' => $customerNumber
            ],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }

    public function testPrzelewy24Order(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::PRZELEWY24;
        $this->paymentHandler = new Przelewy24Payment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
            ->format('Y-m-d');

        $expected = [
            'amount' => (new MollieOrderPriceBuilder())->build($amountTotal, $currencyISO),
            'locale' => $this->localeCode,
            'method' => $paymentMethod,
            'orderNumber' => $orderNumber,
            'payment' => [
                'webhookUrl' => $redirectWebhookUrl,
                'billingEmail' => $this->email
            ],
            'redirectUrl' => $redirectWebhookUrl,
            'webhookUrl' => $redirectWebhookUrl,
            'lines' => $this->getExpectedLineItems($taxStatus, $lineItems, $currency),
            'billingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $this->email),
            'expiresAt' => $expectedOrderLifeTime
        ];

        self::assertSame($expected, $actual);
    }

    public function testSofortOrder(): void
    {
        $redirectWebhookUrl = 'https://foo';
        $this->router->method('generate')->willReturn($redirectWebhookUrl);
        $paymentMethod = PaymentMethod::SOFORT;
        $this->paymentHandler = new SofortPayment($this->loggerService, $this->mollieDoPaymentFacade, $this->molliePaymentFinalize, $this->transitionService);

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

        $actual = $this->builder->build($order, $transactionId, $paymentMethod, 'https://foo', $this->salesChannelContext, $this->paymentHandler, []);

        $expectedOrderLifeTime = (new \DateTime())->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $this->expiresAt))
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
