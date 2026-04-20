<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Refund;

use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use Kiener\MolliePayments\Service\Refund\RefundCreditNoteService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Refund;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

/**
 * @covers \Kiener\MolliePayments\Service\Refund\RefundCreditNoteService
 */
class RefundCreditNoteServiceTest extends TestCase
{
    /**
     * @var TaxCalculator
     */
    private $taxCalculator;

    protected function setUp(): void
    {
        parent::setUp();

        // real Shopware TaxCalculator — no mocking, we want the actual tax math
        $this->taxCalculator = new TaxCalculator();
    }

    /**
     * Regression test: when a partial refund is created the credit note must carry
     * correctly recalculated taxes for the refunded portion, not the full tax of the
     * original line item.
     *
     * Scenario:
     *  - Order line item: 2 x 50 EUR gross = 100 EUR gross, 21% tax
     *  - Refund: 50 EUR (one unit)
     *  - Expected tax on credit note: 50 * 21/121 ≈ 8.68 EUR
     */
    public function testPartialRefundRecalculatesTaxProportionally(): void
    {
        $orderLineItem = $this->createLineItemEntity('line-1', 'T-Shirt', 50.0, 100.0, 2, 21.0);

        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setLineItems(new OrderLineItemCollection([$orderLineItem]));

        $refundRequest = new RefundRequest('', '', '', 50.0);
        $refundRequest->addItem(new RefundRequestItem('line-1', 50.0, 1, 1));

        $capturedUpserts = $this->runCreditNoteCreation($order, $refundRequest, 'refund-1');

        $lineItems = $capturedUpserts[0]['lineItems'];
        self::assertCount(1, $lineItems);

        /** @var CalculatedPrice $price */
        $price = $lineItems[0]['price'];
        self::assertSame(50.0, $price->getTotalPrice());

        /** @var CalculatedTax $calculatedTax */
        $calculatedTax = $price->getCalculatedTaxes()->first();
        self::assertNotNull($calculatedTax);
        self::assertSame(21.0, $calculatedTax->getTaxRate());
        self::assertSame(8.68, round($calculatedTax->getTax(), 2), 'Tax must be recalculated for partial refund');
    }

    /**
     * Full refund: tax for refund amount equals original tax (gross 100 EUR @ 21% → 17.36 EUR).
     */
    public function testFullRefundProducesFullTax(): void
    {
        $orderLineItem = $this->createLineItemEntity('line-1', 'T-Shirt', 50.0, 100.0, 2, 21.0);

        $order = new OrderEntity();
        $order->setId('order-2');
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setLineItems(new OrderLineItemCollection([$orderLineItem]));

        $refundRequest = new RefundRequest('', '', '', 100.0);
        $refundRequest->addItem(new RefundRequestItem('line-1', 100.0, 2, 2));

        $capturedUpserts = $this->runCreditNoteCreation($order, $refundRequest, 'refund-2');

        $lineItems = $capturedUpserts[0]['lineItems'];
        /** @var CalculatedPrice $price */
        $price = $lineItems[0]['price'];
        /** @var CalculatedTax $calculatedTax */
        $calculatedTax = $price->getCalculatedTaxes()->first();

        self::assertSame(17.36, round($calculatedTax->getTax(), 2));
    }

    /**
     * Net orders: tax is calculated on top of the net amount (50 EUR net @ 21% → 10.5 EUR tax).
     */
    public function testNetOrderUsesCalculateNetTaxes(): void
    {
        $orderLineItem = $this->createLineItemEntity('line-1', 'T-Shirt', 50.0, 100.0, 2, 21.0);

        $order = new OrderEntity();
        $order->setId('order-3');
        $order->setTaxStatus(CartPrice::TAX_STATE_NET);
        $order->setLineItems(new OrderLineItemCollection([$orderLineItem]));

        $refundRequest = new RefundRequest('', '', '', 50.0);
        $refundRequest->addItem(new RefundRequestItem('line-1', 50.0, 1, 1));

        $capturedUpserts = $this->runCreditNoteCreation($order, $refundRequest, 'refund-3');

        $lineItems = $capturedUpserts[0]['lineItems'];
        /** @var CalculatedPrice $price */
        $price = $lineItems[0]['price'];
        /** @var CalculatedTax $calculatedTax */
        $calculatedTax = $price->getCalculatedTaxes()->first();

        self::assertSame(10.5, $calculatedTax->getTax(), 'Net orders must use calculateNetTaxes');
    }

    /**
     * Tax-free orders must result in an empty tax collection regardless of amount.
     */
    public function testTaxFreeOrderProducesEmptyTaxCollection(): void
    {
        $orderLineItem = $this->createLineItemEntity('line-1', 'Free item', 50.0, 100.0, 2, 21.0);

        $order = new OrderEntity();
        $order->setId('order-4');
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);
        $order->setLineItems(new OrderLineItemCollection([$orderLineItem]));

        $refundRequest = new RefundRequest('', '', '', 50.0);
        $refundRequest->addItem(new RefundRequestItem('line-1', 50.0, 1, 1));

        $capturedUpserts = $this->runCreditNoteCreation($order, $refundRequest, 'refund-4');

        $lineItems = $capturedUpserts[0]['lineItems'];
        /** @var CalculatedPrice $price */
        $price = $lineItems[0]['price'];

        self::assertSame(0, $price->getCalculatedTaxes()->count());
    }

    /**
     * @return array<mixed>
     */
    private function runCreditNoteCreation(OrderEntity $order, RefundRequest $refundRequest, string $refundId): array
    {
        $capturedUpserts = [];
        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->method('upsert')->willReturnCallback(function ($data) use (&$capturedUpserts) {
            $capturedUpserts = $data;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new RefundCreditNoteService(
            $orderRepository,
            $this->createMock(EntityRepository::class),
            $this->buildSettingsService(),
            $this->taxCalculator,
            new NullLogger()
        );

        $service->createCreditNotes($order, $this->buildFakeRefund($refundId), $refundRequest, $this->createMock(Context::class));

        self::assertNotEmpty($capturedUpserts, 'upsert should have been called');

        return $capturedUpserts;
    }

    private function createLineItemEntity(
        string $id,
        string $label,
        float $unitPrice,
        float $totalPrice,
        int $quantity,
        float $taxRate
    ): OrderLineItemEntity {
        $taxCollection = new CalculatedTaxCollection();
        $taxCollection->add(new CalculatedTax(round($totalPrice * $taxRate / (100 + $taxRate), 2), $taxRate, $totalPrice));

        $taxRules = new TaxRuleCollection([new TaxRule($taxRate)]);

        $price = new CalculatedPrice($unitPrice, $totalPrice, $taxCollection, $taxRules, $quantity);

        $item = new OrderLineItemEntity();
        $item->setId($id);
        $item->setLabel($label);
        $item->setQuantity($quantity);
        $item->setUnitPrice($unitPrice);
        $item->setTotalPrice($totalPrice);
        $item->setPrice($price);

        return $item;
    }

    private function buildSettingsService(): SettingsService
    {
        $settings = new MollieSettingStruct();
        $settings->setRefundManagerCreateCreditNotesEnabled(true);
        $settings->setRefundManagerCreateCreditNotesPrefix('');
        $settings->setRefundManagerCreateCreditNotesSuffix('');

        $settingsService = $this->createMock(SettingsService::class);
        $settingsService->method('getSettings')->willReturn($settings);

        return $settingsService;
    }

    private function buildFakeRefund(string $id): Refund
    {
        $refund = new Refund($this->createMock(MollieApiClient::class));
        $refund->id = $id;

        return $refund;
    }
}
