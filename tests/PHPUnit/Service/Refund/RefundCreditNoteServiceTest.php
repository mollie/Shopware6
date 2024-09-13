<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Refund;


use Kiener\MolliePayments\Service\Refund\Exceptions\CreditNoteException;
use Kiener\MolliePayments\Service\Refund\RefundCreditNoteService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

class RefundCreditNoteServiceTest extends TestCase
{
    /**
     * @var MockObject|EntityRepository
     */
    private $orderRepository;

    /**
     * @var MockObject|EntityRepository
     */
    private $orderLineRepository;

    /**
     * @var MockObject|SettingsService
     */
    private $settingsService;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var MockObject|Context
     */
    private $context;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var string
     */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enabled = true;
        $this->prefix = 'prefix';
        $this->suffix = 'suffix';
        $this->orderRepository = $this->createMock(EntityRepository::class);
        $this->orderLineRepository = $this->createMock(EntityRepository::class);
        $this->settingsService = $this->createMock(SettingsService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->context = $this->createMock(Context::class);
    }

    public function testCreatesLogEntryWhenServiceIsDisabledInConfig(): void
    {
        $this->enabled = false;
        $this->logger->expects($this->once())->method('debug');
        $this->orderRepository->expects($this->never())->method('search');
        $this->orderRepository->expects($this->never())->method('upsert');

        $this->service()->addCreditNoteToOrder('orderId', 'refundId', [], $this->context);
    }

    public function testThrowsExceptionWhenOrderIdIsEmpty(): void
    {
        $this->expectException(CreditNoteException::class);

        $this->service()->addCreditNoteToOrder('', 'refundId', [], $this->context);
    }

    public function testThrowsExceptionWhenRefundIdIsEmpty(): void
    {
        $this->expectException(CreditNoteException::class);

        $this->service()->addCreditNoteToOrder('orderId', '', [], $this->context);
    }

    public function throwsExceptionWhenNoLineItemsAreProvided(): void
    {
        $this->expectException(CreditNoteException::class);

        $this->service()->addCreditNoteToOrder('orderId', 'refundId', [], $this->context);
    }

    /**
     * @throws CreditNoteException
     */
    public function testCanAddRefundLineItems(): void
    {
        $data = [
            ['id' => $lineItemId = Uuid::randomBytes()]
        ];

        $price = $this->createConfiguredMock(CalculatedPrice::class, [
            'getTaxRules' => $rules = $this->createMock(TaxRuleCollection::class)
        ]);
        $lineItemEntity = $this->createConfiguredMock(OrderLineItemEntity::class,
            [
                'getId' => $lineItemId,
                'getIdentifier' => 'lineItemIdentifier',
                'getPrice' => $price,
                'getQuantity' => 1,
                'getTotalPrice' => 9.99,
                'getLabel' => 'label'
            ]
        );
        $searchResult = $this->createConfiguredMock(EntitySearchResult::class, ['first' => $lineItemEntity]);
        $this->orderLineRepository->expects($this->once())->method('search')->willReturn($searchResult);

        $expectedDataArray = [
            'id' => $orderId = 'orderId',
            'lineItems' => [
                [
                    'id' => Uuid::fromStringToHex($lineItemId),
                    'identifier' => Uuid::fromStringToHex('lineItemIdentifier'),
                    'quantity' => 1,
                    'label' => sprintf('%s%s%s', $this->prefix, $lineItemEntity->getLabel(), $this->suffix),
                    'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
                    'price' => new CalculatedPrice(-9.99, -9.99, new CalculatedTaxCollection(), $rules),
                    'priceDefinition' => new QuantityPriceDefinition(-9.99, $rules, 1),
                    'customFields' => [
                        'mollie_payments' => [
                            'type' => 'refund',
                            'refundId' => $refundId = 'refundId',
                            'lineItemId' => $lineItemId
                        ],
                    ],
                ]
            ]
        ];

        $this->logger->expects($this->once())->method('debug');
        $this->orderRepository->expects($this->once())->method('upsert')->with(
            $this->equalTo([$expectedDataArray]),
            $this->equalTo($this->context)
        );

        $this->service()->addCreditNoteToOrder($orderId, $refundId, $data, $this->context);
    }

    public function testThrowsExceptionWhenOrderIdIsEmptyWhenCanceling(): void
    {
        $this->expectException(CreditNoteException::class);

        $this->service()->cancelCreditNoteToOrder('', 'refundId', $this->context);
    }

    public function testThrowsExceptionWhenRefundIdIsEmptyWhenCanceling(): void
    {
        $this->expectException(CreditNoteException::class);

        $this->service()->cancelCreditNoteToOrder('orderId', '', $this->context);
    }

    public function testThrowsExceptionWhenNoCreditLineItemsWouldBeUpserted(): void
    {
        $this->expectException(CreditNoteException::class);

        $data = [
            ['id' => Uuid::randomBytes()]
        ];

        $searchResult = $this->createConfiguredMock(EntitySearchResult::class, ['first' => null]);
        $this->orderLineRepository->expects($this->once())->method('search')->willReturn($searchResult);

        $this->service()->addCreditNoteToOrder('OrderId', 'RefundId', $data, $this->context);
    }

    /**
     * @throws CreditNoteException
     */
    public function testDeletesAssociatedLineItemsWhenCanceling(): void
    {
        $orderId = 'orderId';
        $refundId = 'refundId';

        $searchResult = $this->createConfiguredMock(EntitySearchResult::class, ['first' => $order = $this->createMock(OrderEntity::class)]);
        $this->orderRepository->expects($this->once())->method('search')->willReturn($searchResult);

        $lineItems = new OrderLineItemCollection([
            $lineItem = $this->createConfiguredMock(OrderLineItemEntity::class, [
                'getId' => $expected = Uuid::randomHex(),
                'getCustomFields' => ['mollie_payments' => ['type' => 'refund', 'refundId' => $refundId]]
            ])
        ]);

        $order->expects($this->once())->method('getLineItems')->willReturn($lineItems);

        $this->orderLineRepository->expects($this->once())->method('delete')
            ->with(
                $this->equalTo([['id' => $expected]]),
                $this->equalTo($this->context)
            );

        $this->service()->cancelCreditNoteToOrder($orderId, $refundId, $this->context);
    }
    

    private function service(): RefundCreditNoteService
    {
        $settingsStruct = new MollieSettingStruct();
        $settingsStruct->setRefundManagerCreateCreditNotesEnabled($this->enabled);
        $settingsStruct->setRefundManagerCreateCreditNotesPrefix($this->prefix);
        $settingsStruct->setRefundManagerCreateCreditNotesSuffix($this->suffix);

        $this->settingsService->method('getSettings')->willReturn($settingsStruct);

        return new RefundCreditNoteService(
            $this->orderRepository,
            $this->orderLineRepository,
            $this->settingsService,
            $this->logger
        );
    }
}