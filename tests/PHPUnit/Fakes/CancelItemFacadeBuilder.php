<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Components\CancelManager\CancelItemFacade;
use Kiener\MolliePayments\Components\CancelManager\CancelManagerInterface;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\OrderLineCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @final
 */
class CancelItemFacadeBuilder
{

    /** @var MollieApiClient */
    private $mollieClient;

    /** @var TestCase  */
    private $testCase;

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;

        $this->mollieClient = $testCase->getMockBuilder(MollieApiClient::class)->disableOriginalConstructor()->getMock();

    }

    public function withInvalidOrder(): self
    {

        $mockOrderEndpoint = $this->testCase->getMockBuilder(OrderEndpoint::class)->disableOriginalConstructor()->getMock();
        $mockOrderEndpoint->method('get')->willThrowException(new ApiException('Invalid order'));

        $this->mollieClient->orders = $mockOrderEndpoint;

        return $this;
    }

    public function withDefaultOrder(): self
    {
        $mockOrderLine = $this->testCase->getMockBuilder(OrderLine::class)->disableOriginalConstructor()->getMock();
        $mockOrderLine->cancelableQuantity = 2;
        $mockOrderLine->id = 'valid';

        $oderLineCollection = new OrderLineCollection(1, null);
        $oderLineCollection[0] = $mockOrderLine;

        $mockOrder = $this->testCase->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $mockOrder->method('lines')->willReturn($oderLineCollection);


        $mockOrderEndpoint = $this->testCase->getMockBuilder(OrderEndpoint::class)->disableOriginalConstructor()->getMock();
        $mockOrderEndpoint->method('get')->willReturn($mockOrder);

        $this->mollieClient->orders = $mockOrderEndpoint;

        return $this;
    }

    public function bild(): CancelItemFacade
    {
        /** @var MollieApiFactory $mollieFactory */
        $mollieFactory = $this->testCase->getMockBuilder(MollieApiFactory::class)->disableOriginalConstructor()->getMock();
        $mollieFactory->method('getClient')->willReturn($this->mollieClient);
        return new CancelItemFacade($mollieFactory, new NullLogger());
    }

}