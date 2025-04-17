<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderLineIdException;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\OrderDeliveryService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Service\UpdateOrderTransactionCustomFields;
use MolliePayments\Tests\Fakes\Repositories\FakeOrderRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class OrderServiceTest extends TestCase
{
    /**
     * @var OrderEntity
     */
    private $testOrder;

    private $orderRepository;
    private $orderService;

    protected function setUp(): void
    {
        $this->testOrder = new OrderEntity();
        $this->testOrder->setId('id-123');
        $this->testOrder->setOrderNumber('nr-123');

        $this->orderRepository = new FakeOrderRepository($this->testOrder);

        $this->orderService = new OrderService(
            $this->orderRepository,
            $this->createMock(\Kiener\MolliePayments\Service\MollieApi\Order::class),
            $this->createMock(UpdateOrderCustomFields::class),
            $this->createMock(UpdateOrderTransactionCustomFields::class),
            $this->createMock(OrderDeliveryService::class),
            $this->createMock(ContainerInterface::class),
            new NullLogger()
        );
    }

    /**
     * This test verifies that we get a found order from the repository
     * and also that our passed criteria arguments contain the order ID that we use as argument.
     */
    public function testGetOrderFindsEntity(): void
    {
        $foundOrder = $this->orderService->getOrder('id-123', Context::createDefaultContext());

        $this->assertSame($this->testOrder, $foundOrder);

        $receivedCriteria = $this->orderRepository->getCriteriaSearch();

        $this->assertInstanceOf(Criteria::class, $receivedCriteria);
        $this->assertContains('id-123', $receivedCriteria->getIds());
    }

    /**
     * This test verifies that our service correctly returns
     * a found order entity when searching by order number.
     */
    public function testGetOrderByNumberReturnsOrder(): void
    {
        $foundOrder = $this->orderService->getOrderByNumber('not-used', Context::createDefaultContext());

        $this->assertSame($this->testOrder, $foundOrder);
    }

    /**
     * This test verifies that our repository gets the correctly built filter criteria.
     * The service builds a new equalsFilter with the orderNumber.
     * We extract this filter and verify that our inputArgument is used as value in this filter.
     */
    public function testGetOrderByNumberUsesCorrectCriteria(): void
    {
        $orderNumber = 'nr-123';

        $this->orderService->getOrderByNumber($orderNumber, Context::createDefaultContext());

        $receivedCriteria = $this->orderRepository->getCriteriaSearchIDs();

        $this->assertInstanceOf(Criteria::class, $receivedCriteria);

        // search all orderNumber "equals" filter, and just extract the sent-value.
        // this helps us to verify that our correct $orderNumber has been used.
        $sentOrderNumberValues = [];
        foreach ($receivedCriteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'orderNumber') {
                $sentOrderNumberValues[] = $filter->getValue();
            }
        }

        $this->assertTrue($receivedCriteria->hasEqualsFilter('orderNumber'));
        $this->assertContains($orderNumber, $sentOrderNumberValues, 'no orderNumber filter has been found with a correct value');
    }

    public function testGetMollieOrderId()
    {
        $order = $this->createConfiguredMock(OrderEntity::class, [
            'getCustomFields' => [
                CustomFieldsInterface::MOLLIE_KEY => [
                    CustomFieldsInterface::ORDER_KEY => 'fizz',
                ],
            ],
        ]);

        $actualValue = $this->orderService->getMollieOrderId($order);
        $this->assertEquals('fizz', $actualValue);
    }

    public function testGetMollieOrderIdDoesntExist()
    {
        $order = $this->createConfiguredMock(OrderEntity::class, [
            'getOrderNumber' => 'not_important',
            'getCustomFields' => null,
        ]);

        $this->expectException(CouldNotExtractMollieOrderIdException::class);
        $this->orderService->getMollieOrderId($order);
    }

    public function testGetMollieOrderLineId()
    {
        $orderLineItem = $this->createConfiguredMock(OrderLineItemEntity::class, [
            'getCustomFields' => [
                CustomFieldsInterface::MOLLIE_KEY => [
                    CustomFieldsInterface::ORDER_LINE_KEY => 'buzz',
                ],
            ],
        ]);

        $actualValue = $this->orderService->getMollieOrderLineId($orderLineItem);
        $this->assertEquals('buzz', $actualValue);
    }

    public function testGetMollieOrderLineIdDoesntExist()
    {
        $orderLineItem = $this->createConfiguredMock(OrderLineItemEntity::class, [
            'getId' => 'not_important',
            'getCustomFields' => null,
        ]);

        $this->expectException(CouldNotExtractMollieOrderLineIdException::class);
        $this->orderService->getMollieOrderLineId($orderLineItem);
    }

    private function setUpOrderRepositoryWithoutOrder()
    {
        $searchResult = $this->createConfiguredMock(EntitySearchResult::class, [
            'first' => null,
        ]);

        $idSearchResult = $this->createConfiguredMock(IdSearchResult::class, [
            'firstId' => null,
        ]);

        $this->orderRepository->entitySearchResults[] = $searchResult;
        $this->orderRepository->idSearchResults[] = $idSearchResult;

        $this->orderRepository->entitySearchResults[] = $searchResult;
    }
}
