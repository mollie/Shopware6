<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Component\Refund;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\CreditNoteService;
use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use MolliePayments\Shopware\Tests\Fakes\FakeEntityRepository;
use MolliePayments\Shopware\Tests\Fakes\FakeRecalculationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

#[CoversClass(CreditNoteService::class)]
class CreditNoteServiceTest extends TestCase
{
    private FakeRecalculationService $recalculationService;
    private FakeEntityRepository $orderRepository;
    private FakeEntityRepository $lineItemRepository;
    private Context $context;
    private RefundSettings $settings;

    protected function setUp(): void
    {
        $this->recalculationService = new FakeRecalculationService();
        $this->orderRepository = new FakeEntityRepository(new OrderDefinition());
        $this->lineItemRepository = new FakeEntityRepository(new OrderLineItemDefinition());
        $this->context = new Context(new SystemSource());
        $this->settings = new RefundSettings();
    }

    /**
     * Mollie refund amounts are always gross. For net orders AbsolutePriceDefinition
     * treats its value as net and would add VAT on top — inflating the credit note total.
     * The service must scale the gross amount to its net equivalent using the order ratio
     * so the resulting credit note gross total equals the actual Mollie refund amount.
     */
    public function testNetOrderScalesGrossAmountToNetEquivalent(): void
    {
        $order = $this->buildOrder(CartPrice::TAX_STATE_NET, amountNet: 100.0, amountTotal: 119.0);
        $refund = $this->buildRefund('re_001', 5.95);

        $this->buildService()->addCreditNote($order, $refund, $this->settings, $this->context);

        $definition = $this->capturedPriceDefinition();
        self::assertSame(-5.0, $definition->getPrice());
    }

    /**
     * A partial custom-amount refund on a net order must also be scaled:
     * the Mollie amount represents the gross refund, not the net price.
     */
    public function testNetOrderScalesPartialRefundAmountToNet(): void
    {
        $order = $this->buildOrder(CartPrice::TAX_STATE_NET, amountNet: 100.0, amountTotal: 119.0);
        $refund = $this->buildRefund('re_002', 119.0);

        $this->buildService()->addCreditNote($order, $refund, $this->settings, $this->context);

        $definition = $this->capturedPriceDefinition();
        self::assertSame(-100.0, $definition->getPrice());
    }

    /**
     * For gross orders the Mollie amount is the gross price already, so it is passed
     * to AbsolutePriceDefinition unchanged.
     */
    public function testGrossOrderPassesAmountAsIs(): void
    {
        $order = $this->buildOrder(CartPrice::TAX_STATE_GROSS, amountNet: 5.0, amountTotal: 5.95);
        $refund = $this->buildRefund('re_003', 5.95);

        $this->buildService()->addCreditNote($order, $refund, $this->settings, $this->context);

        $definition = $this->capturedPriceDefinition();
        self::assertSame(-5.95, $definition->getPrice());
    }

    /**
     * Tax-free orders carry no VAT, so the amount reaches the price definition unchanged.
     */
    public function testTaxFreeOrderPassesAmountAsIs(): void
    {
        $order = $this->buildOrder(CartPrice::TAX_STATE_FREE, amountNet: 5.0, amountTotal: 5.0);
        $refund = $this->buildRefund('re_004', 5.0);

        $this->buildService()->addCreditNote($order, $refund, $this->settings, $this->context);

        $definition = $this->capturedPriceDefinition();
        self::assertSame(-5.0, $definition->getPrice());
    }

    /**
     * A zero-value refund must not add any credit line item to the order.
     */
    public function testZeroAmountSkipsAddCustomLineItem(): void
    {
        $order = $this->buildOrder(CartPrice::TAX_STATE_GROSS, amountNet: 5.0, amountTotal: 5.95);
        $refund = $this->buildRefund('re_005', 0.0);

        $this->buildService()->addCreditNote($order, $refund, $this->settings, $this->context);

        self::assertNull($this->recalculationService->capturedLineItem);
    }

    /**
     * When the matching credit note line item exists, cancelCreditNote must delete it
     * and trigger a recalculation so the order totals stay consistent.
     */
    public function testCancelCreditNoteDeletesLineItemAndRecalculates(): void
    {
        $this->lineItemRepository->idSearchResults[] = new IdSearchResult(
            1,
            [['primaryKey' => 'credit-note-abc', 'data' => []]],
            new Criteria(),
            $this->context
        );
        $this->lineItemRepository->entityWrittenContainerEvents[] = new EntityWrittenContainerEvent(
            $this->context,
            new NestedEventCollection(),
            []
        );

        $this->buildService()->cancelCreditNote('order-id', 'mollie-refund-id', $this->context);

        self::assertSame([['id' => 'credit-note-abc']], $this->lineItemRepository->data[0]);
        self::assertTrue($this->recalculationService->recalculateCalled);
    }

    /**
     * When no credit note exists for the given refund, cancelCreditNote must do nothing
     * — no delete and no unnecessary recalculation.
     */
    public function testCancelCreditNoteSkipsWhenNoCreditNoteFound(): void
    {
        $this->lineItemRepository->idSearchResults[] = new IdSearchResult(
            0,
            [],
            new Criteria(),
            $this->context
        );

        $this->buildService()->cancelCreditNote('order-id', 'mollie-refund-id', $this->context);

        self::assertEmpty($this->lineItemRepository->data);
        self::assertFalse($this->recalculationService->recalculateCalled);
    }

    private function buildService(): CreditNoteService
    {
        return new CreditNoteService(
            $this->orderRepository,
            $this->lineItemRepository,
            $this->recalculationService,
            new NullLogger(),
        );
    }

    private function buildOrder(string $taxStatus, float $amountNet, float $amountTotal): OrderEntity
    {
        $customer = CustomerBuilder::create()->build();
        $order = (new OrderEntityBuilder())->getDefaultOrder($customer);
        $order->setTaxStatus($taxStatus);
        $order->setAmountNet($amountNet);
        $order->setAmountTotal($amountTotal);

        return $order;
    }

    private function buildRefund(string $id, float $amount): MollieRefund
    {
        return new MollieRefund(
            $id,
            'tr_payment',
            RefundStatus::Queued,
            new Money($amount, 'EUR'),
            '',
            new \DateTimeImmutable('2024-01-01'),
        );
    }

    private function capturedPriceDefinition(): AbsolutePriceDefinition
    {
        $lineItem = $this->recalculationService->capturedLineItem;

        self::assertNotNull($lineItem, 'Expected addCustomLineItem to be called');

        $definition = $lineItem->getPriceDefinition();
        self::assertInstanceOf(AbsolutePriceDefinition::class, $definition);

        return $definition;
    }
}
