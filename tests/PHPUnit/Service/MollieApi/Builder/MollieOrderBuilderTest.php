<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

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
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use MolliePayments\Tests\Traits\OrderTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

class MollieOrderBuilderTest extends TestCase
{
    use OrderTrait;

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
     * @var CustomerAddressEntity
     */
    private $address;

    public function setUp(): void
    {
        $this->address = $this->getDummyAddress();
        /** @var SettingsService settingsService */
        $this->settingsService = $this->getMockBuilder(SettingsService::class)->disableOriginalConstructor()->getMock();
        /** @var LoggerService loggerService */
        $this->loggerService = $this->getMockBuilder(LoggerService::class)->disableOriginalConstructor()->getMock();
        /** @var OrderDataExtractor customerService */
        $this->orderDataExtractor = $this->getMockBuilder(OrderDataExtractor::class)->disableOriginalConstructor()->getMock();
        /** @var Router router */
        $this->router = $this->getMockBuilder(RouterInterface::class)->disableOriginalConstructor()->getMock();

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

    public function testCreditCardOrder(): void
    {
        $lineItemOne = $this->getOrderLineItem();
        $lineItemTwo = $this->getOrderLineItem();
        $email = 'email@email.de';
        $orderLifeTime = '2021-06-15';
        $this->settingsService->method('getOrderLifetimeDate')->willReturn($orderLifeTime);

        $expected = [
            'billingAddress' => $this->getExpectedTestAddress($this->address, $email),
            'shippingAddress' => $this->getExpectedTestAddress($this->address, $email),
            'expiresAt' => $orderLifeTime
        ];

    }
}
