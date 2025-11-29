<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Hydrator\MollieLineItemHydrator;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderAddressBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieShippingLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\Fixer\RoundingDifferenceFixer;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\MollieOrderCustomerEnricher;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Service\MollieLocaleService;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\Router\RoutingDetector;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Service\UrlParsingService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use Mollie\Shopware\Component\Payment\FinalizeAction;
use Mollie\Shopware\Component\Payment\PayAction;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;
use MolliePayments\Shopware\Tests\Fakes\FakeEventDispatcher;
use MolliePayments\Shopware\Tests\Fakes\FakePluginSettings;
use MolliePayments\Shopware\Tests\Traits\OrderTrait;
use MolliePayments\Shopware\Tests\Utils\Traits\PaymentBuilderTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

abstract class AbstractMollieOrderBuilder extends TestCase
{
    use OrderTrait;
    use PaymentBuilderTrait;

    /**
     * @var MockObject|SettingsService
     */
    protected $settingsService;
    /**
     * @var LoggerInterface
     */
    protected $loggerService;
    /**
     * @var MockObject|OrderDataExtractor
     */
    protected $orderDataExtractor;
    /**
     * @var MockObject|RouterInterface
     */
    protected $router;
    /**
     * @var MollieOrderBuilder
     */
    protected $builder;
    /**
     * @var MockObject|SalesChannelContext
     */
    protected $salesChannelContext;
    /**
     * @var PaymentHandler
     */
    protected $paymentHandler;
    /**
     * @var CustomerEntity
     */
    protected $customer;
    /**
     * @var CustomerAddressEntity
     */
    protected $address;
    /**
     * @var string
     */
    protected $email;
    /**
     * @var int
     */
    protected $expiresAt;
    /**
     * @var string
     */
    protected $localeCode;
    /**
     * @var MockObject|MolliePaymentDoPay
     */
    protected $mollieDoPaymentFacade;
    /**
     * @var MockObject|MolliePaymentFinalize
     */
    protected $molliePaymentFinalize;
    /**
     * @var MockObject|TransactionTransitionServiceInterface
     */
    protected $transitionService;
    /**
     * @var MollieSettingStruct
     */
    protected $settingStruct;

    protected PayAction $payAction;
    protected FinalizeAction $finalizeAction;

    /**
     * @var MockObject|MollieLocaleService
     */
    private $mollieLocaleService;

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

        /* @var OrderDataExtractor customerService */
        $this->orderDataExtractor = $this->getMockBuilder(OrderDataExtractor::class)->disableOriginalConstructor()->getMock();
        $this->orderDataExtractor->method('extractCustomer')->willReturn($this->customer);
        $this->orderDataExtractor->method('extractLocale')->willReturn($locale);

        /* @var SettingsService settingsService */
        $this->settingsService = $this->getMockBuilder(SettingsService::class)->disableOriginalConstructor()->getMock();
        $this->settingStruct = new MollieSettingStruct();
        $this->settingStruct->assign([
            'createCustomersAtMollie' => false,
            'orderLifetimeDays' => $this->expiresAt,
        ]);
        $this->settingsService->method('getSettings')->willReturn($this->settingStruct);

        $this->loggerService = new NullLogger();

        /* @var SalesChannelContext salesChannelContext */
        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        /* @var Router router */
        $this->router = $this->getMockBuilder(RouterInterface::class)->disableOriginalConstructor()->getMock();
        /* @var MolliePaymentDoPay $mollieDoPaymentFacade */
        $this->mollieDoPaymentFacade = $this->getMockBuilder(MolliePaymentDoPay::class)->disableOriginalConstructor()->getMock();
        /* @var MolliePaymentFinalize $molliePaymentFianlize */
        $this->molliePaymentFinalize = $this->getMockBuilder(MolliePaymentFinalize::class)->disableOriginalConstructor()->getMock();
        /* @var TransactionTransitionServiceInterface $transitionService */
        $this->transitionService = $this->getMockBuilder(TransactionTransitionService::class)->disableOriginalConstructor()->getMock();

        $transactionConverter = $this->createMock(TransactionConverterInterface::class);

        $routingDetector = new RoutingDetector(new RequestStack([]));
        $routingBuilder = new RoutingBuilder(
            $this->router,
            $routingDetector,
            new FakePluginSettings(''),
            ''
        );

        $this->mollieLocaleService = new MollieLocaleService($this->createMock(EntityRepository::class));

        $this->builder = new MollieOrderBuilder(
            $this->settingsService,
            $this->orderDataExtractor,
            new MollieOrderPriceBuilder(),
            new MollieLineItemBuilder(
                new IsOrderLineItemValid(),
                new PriceCalculator(),
                new LineItemDataExtractor(new UrlParsingService()),
                new RoundingDifferenceFixer(),
                new MollieLineItemHydrator(new MollieOrderPriceBuilder()),
                new MollieShippingLineItemBuilder(new PriceCalculator())
            ),
            new MollieOrderAddressBuilder(),
            new MollieOrderCustomerEnricher($this->createMock(CustomerService::class)),
            $routingBuilder,
            $this->mollieLocaleService,
            new FakeEventDispatcher(),
            $this->loggerService
        );

        $this->payAction = new PayAction($this->mollieDoPaymentFacade, $transactionConverter, $this->transitionService, $this->loggerService);
        $this->finalizeAction = new FinalizeAction($this->molliePaymentFinalize, $transactionConverter, $this->loggerService);
    }
}
