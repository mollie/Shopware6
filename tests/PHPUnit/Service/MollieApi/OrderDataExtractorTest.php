<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\OrderCurrencyNotFoundException;
use Kiener\MolliePayments\Exception\OrderCustomerNotFoundException;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class OrderDataExtractorTest extends TestCase
{
    /** @var LoggerInterface */
    private $loggerService;

    /** @var CustomerService|\PHPUnit\Framework\MockObject\MockObject */
    private $customerService;

    /** @var OrderDataExtractor */
    private $extractor;

    /** @var \PHPUnit\Framework\MockObject\MockObject|SalesChannelContext */
    private $salesChannelContext;

    /** @var Context|\PHPUnit\Framework\MockObject\MockObject */
    private $context;

    public function setUp(): void
    {
        $this->loggerService = new NullLogger();
        $this->customerService = $this->getMockBuilder(CustomerService::class)->disableOriginalConstructor()->getMock();
        $this->extractor = new OrderDataExtractor($this->loggerService, $this->customerService);
        $this->context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->salesChannelContext->method('getContext')->willReturn($this->context);
    }

    /**
     * tests that an exception is thrown if order has no customer
     * should be logged
     */
    public function testExtractCustomerMissingCustomerThrowsException(): void
    {
        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $this->expectException(OrderCustomerNotFoundException::class);

        $this->extractor->extractCustomer($order, $this->salesChannelContext);
    }

    /**
     * tests that we fetch the customer from database, to get an enriched CustomerEntity
     * with all customer data we need
     * if customer returns no CustomerEntity error is thrown and logged
     */
    public function testExtractCustomerQueryDatabaseForCustomer(): void
    {
        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $orderCustomerEntity = new OrderCustomerEntity();
        $customerId = Uuid::randomHex();
        $orderCustomerEntity->setCustomerId($customerId);
        $order->setOrderCustomer($orderCustomerEntity);

        $this->expectException(OrderCustomerNotFoundException::class);

        $this->customerService->expects($this->once())->method('getCustomer')->with($customerId, $this->context);
        $this->extractor->extractCustomer($order, $this->salesChannelContext);
    }

    /**
     * if customer could be found in database, it should be returned
     */
    public function testExtractCustomerFoundCustomerEntityIsReturned(): void
    {
        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $orderCustomerEntity = new OrderCustomerEntity();
        $customerId = Uuid::randomHex();
        $orderCustomerEntity->setCustomerId($customerId);
        $order->setOrderCustomer($orderCustomerEntity);

        $expectedCustomer = new CustomerEntity();
        $expectedCustomer->setId($customerId);
        $this->customerService->method('getCustomer')->with($customerId, $this->context)->willReturn($expectedCustomer);

        self::assertSame($expectedCustomer, $this->extractor->extractCustomer($order, $this->salesChannelContext));
    }

    public function testExtractCurrency(): void
    {
        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $expectedCurrency = new CurrencyEntity();
        $expectedCurrency->setId(Uuid::randomHex());
        $order->setCurrency($expectedCurrency);

        self::assertSame($expectedCurrency, $this->extractor->extractCurrency($order, $this->salesChannelContext));
    }

    public function testExtractCurrencyOrderCurrencyIsMissing(): void
    {
        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);

        $this->expectException(OrderCurrencyNotFoundException::class);

        $this->extractor->extractCurrency($order, $this->salesChannelContext);
    }

    public function testExtractLocale(): void
    {
        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $expectedLocale = new LocaleEntity();
        $expectedLocale->setId(Uuid::randomHex());
        $language->setLocale($expectedLocale);
        $order->setLanguage($language);

        self::assertSame($expectedLocale, $this->extractor->extractLocale($order, $this->salesChannelContext));
    }

    public function testExtractLocaleOrderLocaleIsMissingFallbackSalesChannel(): void
    {
        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $order->setLanguage($language);
        $languageTwo = new LanguageEntity();
        $languageTwo->setId(Uuid::randomHex());
        $expectedLocale = new LocaleEntity();
        $expectedLocale->setId(Uuid::randomHex());
        $languageTwo->setLocale($expectedLocale);
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId(Uuid::randomHex());
        $salesChannelEntity->setLanguage($languageTwo);
        $this->salesChannelContext->method('getSalesChannel')->willReturn($salesChannelEntity);

        self::assertSame($expectedLocale, $this->extractor->extractLocale($order, $this->salesChannelContext));
    }

    public function testExtractLocaleOrderLocaleReturnsNullIfLocaleCouldBeFound(): void
    {
        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $order->setLanguage($language);
        $languageTwo = new LanguageEntity();
        $languageTwo->setId(Uuid::randomHex());
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId(Uuid::randomHex());
        $salesChannelEntity->setLanguage($languageTwo);
        $this->salesChannelContext->method('getSalesChannel')->willReturn($salesChannelEntity);

        self::assertNull($this->extractor->extractLocale($order, $this->salesChannelContext));
    }
}
