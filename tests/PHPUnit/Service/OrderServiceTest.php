<?php

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderLineIdException;
use Kiener\MolliePayments\Exception\OrderNumberNotFoundException;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\OrderService;
use MolliePayments\Tests\Fakes\FakeEntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService as ShopwareOrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class OrderServiceTest extends TestCase
{
    private $orderRepository;
    private $orderService;

    protected function setUp(): void
    {
        $this->orderRepository = new FakeEntityRepository(new OrderDefinition());

        $this->orderService = new OrderService(
            $this->orderRepository,
            $this->createMock(ShopwareOrderService::class),
            new NullLogger()
        );
    }

    private function setUpOrderRepositoryWithOrder()
    {
        $searchResult = $this->createConfiguredMock(EntitySearchResult::class, [
            'first' => $this->createConfiguredMock(OrderEntity::class, [
                'getId' => 'foo'
            ])
        ]);

        $idSearchResult = $this->createConfiguredMock(IdSearchResult::class, [
            'firstId' => 'foo'
        ]);

        $this->orderRepository->entitySearchResults[] = $searchResult;
        $this->orderRepository->idSearchResults[] = $idSearchResult;
    }

    private function setUpOrderRepositoryWithoutOrder()
    {
        $searchResult = $this->createConfiguredMock(EntitySearchResult::class, [
            'first' => null
        ]);

        $idSearchResult = $this->createConfiguredMock(IdSearchResult::class, [
            'firstId' => null
        ]);

        $this->orderRepository->entitySearchResults[] = $searchResult;
        $this->orderRepository->idSearchResults[] = $idSearchResult;

        $this->orderRepository->entitySearchResults[] = $searchResult;
    }

    public function testGetOrder()
    {
        $this->setUpOrderRepositoryWithOrder();

        $actualResult = $this->orderService->getOrder('foo', Context::createDefaultContext());

        $this->assertNotNull($actualResult);
        $this->assertInstanceOf(OrderEntity::class, $actualResult);
        $this->assertContainsOnlyInstancesOf(Criteria::class, $this->orderRepository->criteria);

        /** @var Criteria $receivedCriteria */
        $receivedCriteria = $this->orderRepository->criteria[0];

        $this->assertContains('foo', $receivedCriteria->getIds());
    }

    public function testGetOrderDoesntExist()
    {
        $this->setUpOrderRepositoryWithoutOrder();

        $this->expectException(OrderNotFoundException::class);
        $this->orderService->getOrder('foo', Context::createDefaultContext());
    }

    public function testGetOrderByNumber()
    {
        $this->setUpOrderRepositoryWithOrder();

        $actualResult = $this->orderService->getOrderByNumber('bar', Context::createDefaultContext());

        $this->assertNotNull($actualResult);
        $this->assertInstanceOf(OrderEntity::class, $actualResult);
        $this->assertContainsOnlyInstancesOf(Criteria::class, $this->orderRepository->criteria);

        /** @var Criteria $receivedCriteria */
        $receivedCriteria = $this->orderRepository->criteria[0];

        $this->assertTrue($receivedCriteria->hasEqualsFilter('orderNumber'));

        $orderNumberFilters = array_filter($receivedCriteria->getFilters(), static function (Filter $filter) {
            return $filter instanceof EqualsFilter && $filter->getField() === 'orderNumber';
        });

        $orderNumberFilterValues = array_map(static function (EqualsFilter $filter) {
            return $filter->getValue();
        }, $orderNumberFilters);

        $this->assertContains('bar', $orderNumberFilterValues);
    }

    public function testGetOrderByNumberDoesntExist()
    {
        $this->setUpOrderRepositoryWithoutOrder();

        $this->expectException(OrderNumberNotFoundException::class);
        $this->orderService->getOrderByNumber('bar', Context::createDefaultContext());
    }

    public function testGetMollieOrderId()
    {
        $order = $this->createConfiguredMock(OrderEntity::class, [
            'getCustomFields' => [
                CustomFieldsInterface::MOLLIE_KEY => [
                    CustomFieldsInterface::ORDER_KEY => 'fizz'
                ]
            ]
        ]);

        $actualValue = $this->orderService->getMollieOrderId($order);
        $this->assertEquals('fizz', $actualValue);
    }

    public function testGetMollieOrderIdDoesntExist()
    {
        $order = $this->createConfiguredMock(OrderEntity::class, [
            'getOrderNumber' => 'not_important',
            'getCustomFields' => null
        ]);

        $this->expectException(CouldNotExtractMollieOrderIdException::class);
        $this->orderService->getMollieOrderId($order);
    }

    public function testGetMollieOrderLineId()
    {
        $orderLineItem = $this->createConfiguredMock(OrderLineItemEntity::class, [
            'getCustomFields' => [
                CustomFieldsInterface::MOLLIE_KEY => [
                    CustomFieldsInterface::ORDER_LINE_KEY => 'buzz'
                ]
            ]
        ]);

        $actualValue = $this->orderService->getMollieOrderLineId($orderLineItem);
        $this->assertEquals('buzz', $actualValue);
    }

    public function testGetMollieOrderLineIdDoesntExist()
    {
        $orderLineItem = $this->createConfiguredMock(OrderLineItemEntity::class, [
            'getId' => 'not_important',
            'getCustomFields' => null
        ]);

        $this->expectException(CouldNotExtractMollieOrderLineIdException::class);
        $this->orderService->getMollieOrderLineId($orderLineItem);
    }
}
