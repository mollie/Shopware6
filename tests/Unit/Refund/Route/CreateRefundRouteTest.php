<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Route;

use Mollie\Shopware\Component\FlowBuilder\Event\Refund\RefundStartedEvent;
use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\Event\ModifyCreateRefundPayloadEvent;
use Mollie\Shopware\Component\Refund\Route\CreateRefundRoute;
use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * What leaves the shop towards Mollie when a refund is created, and what the plugin writes down
 * about it.
 */
#[CoversClass(CreateRefundRoute::class)]
final class CreateRefundRouteTest extends RefundRouteTestCase
{
    public function testCreateWithoutAmountAndItemsAsksTheBuilderForAFullRefund(): void
    {
        $this->givenOrder();

        $this->create([]);

        $call = $this->refundBuilder->getLastCall();
        $this->assertNull($call['amount']);
        $this->assertSame([], $call['items']);
    }

    public function testCreateStoresAFullRefundWithItsType(): void
    {
        $this->givenOrder();

        $this->create([]);

        $this->assertSame('FULL', $this->storedRefundRow()['type']);
    }

    public function testCreateStoresACustomAmountRefundAsPartial(): void
    {
        $this->givenOrder();

        $this->create(['amount' => 5.0]);

        $this->assertSame('PARTIAL', $this->storedRefundRow()['type']);
    }

    public function testCreatePassesTheRequestedAmountToTheBuilder(): void
    {
        $this->givenOrder();

        $this->create(['amount' => '7.25']);

        $this->assertSame(7.25, $this->refundBuilder->getLastCall()['amount']);
    }

    /**
     * The admin posts a row for every cart position, most of them untouched. Only the rows the
     * merchant actually filled in may reach the builder, otherwise a full refund is built instead.
     */
    public function testCreateDropsRequestedItemsWithoutQuantityAndWithoutAmount(): void
    {
        $this->givenOrder();

        $this->create(['items' => [
            ['id' => self::LINE_ITEM_ID, 'quantity' => 1, 'amount' => 0.0, 'resetStock' => 0],
            ['id' => self::DELIVERY_ID, 'quantity' => 0, 'amount' => 0.0, 'resetStock' => 0],
        ]]);

        $items = $this->refundBuilder->getLastCall()['items'];
        $this->assertCount(1, $items);
        $this->assertSame(self::LINE_ITEM_ID, $items[0]['id']);
    }

    public function testCreateKeepsARequestedItemThatOnlyCarriesAnAmount(): void
    {
        $this->givenOrder();

        $this->create(['items' => [
            ['id' => self::LINE_ITEM_ID, 'quantity' => 0, 'amount' => 3.5, 'resetStock' => 0],
        ]]);

        $this->assertCount(1, $this->refundBuilder->getLastCall()['items']);
    }

    public function testCreateStoresTheReturnIdAsRefundMetadata(): void
    {
        $this->givenOrder();

        $this->create(['returnId' => 'return-42']);

        $created = $this->refundGateway->getCreatedRefunds();
        $this->assertSame(['swagReturnId' => 'return-42'], $created[0]->getMetadata());
    }

    public function testCreateSendsNoMetadataWithoutAReturnId(): void
    {
        $this->givenOrder();

        $this->create([]);

        $created = $this->refundGateway->getCreatedRefunds();
        $this->assertSame([], $created[0]->getMetadata());
    }

    /**
     * The payload event is an extension point: whatever a listener returns is what must reach
     * Mollie, not the payload the builder produced.
     */
    public function testCreateSendsThePayloadTheModifyEventCarries(): void
    {
        $this->givenOrder();

        $replacement = new CreatePaymentRefund('tr_1', new Money(3.0, 'EUR'), 'replaced');
        $this->eventDispatcher->addListener(
            ModifyCreateRefundPayloadEvent::class,
            function (ModifyCreateRefundPayloadEvent $event) use ($replacement): void {
                $event->setCreateRefund($replacement);
            }
        );

        $this->create([]);

        $created = $this->refundGateway->getCreatedRefunds();
        $this->assertSame('replaced', $created[0]->getDescription());
    }

    public function testCreateDispatchesTheRefundStartedEventWithTheAmountMollieConfirmed(): void
    {
        $this->givenOrder();
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 12.5, RefundStatus::Pending));

        $this->create([]);

        $event = $this->firstEventOfType(RefundStartedEvent::class);
        $this->assertSame(12.5, $event->getAmount());
    }

    /**
     * Mollie answers with the id of the refund. It has to land in the payment extension, so an
     * accounting export finds it in the custom fields of the order.
     */
    public function testCreateRecordsTheMollieRefundIdOnThePaymentExtension(): void
    {
        $molliePayment = $this->molliePayment();
        $this->givenOrder(molliePayment: $molliePayment);
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 5.0, RefundStatus::Pending));

        $this->create([]);

        $saved = $this->transactionService->getSavedPaymentExtensions();
        $this->assertSame(['re_new'], $saved[0]['payment']->getRefundIds());
        $this->assertSame('transaction-1', $saved[0]['transactionId']);
    }

    public function testCreateAddsACreditNoteWhenTheSettingIsEnabled(): void
    {
        $this->givenOrder();
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 5.0, RefundStatus::Pending));

        $this->create([], new RefundSettings(createCreditNotes: true));

        $this->assertNotNull($this->recalculationService->capturedLineItem);
    }

    public function testCreateAddsNoCreditNoteWhenTheSettingIsDisabled(): void
    {
        $this->givenOrder();
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 5.0, RefundStatus::Pending));

        $this->create([]);

        $this->assertNull($this->recalculationService->capturedLineItem);
    }

    public function testCreateAnswersWithTheRecalculatedTotals(): void
    {
        $freshPayment = $this->freshPayment([$this->mollieRefund('re_new', 5.0, RefundStatus::Refunded)]);
        $this->givenOrder(freshPayment: $freshPayment);
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 5.0, RefundStatus::Refunded));

        $body = $this->create([]);

        $this->assertSame(5.0, (float) $body['totals']['refunded']);
        $this->assertSame(20.0, (float) $body['totals']['remaining']);
    }

    public function testCreateFailsWithoutAMolliePaymentOnTheTransaction(): void
    {
        $this->givenOrder(molliePayment: null);

        $this->expectExceptionMessage('No Mollie payment extension found for order "order-1"');

        $this->create([]);
    }

    public function testCreateFailsForAnUnknownOrder(): void
    {
        $this->expectExceptionMessage('Order "order-1" not found');

        $this->create([]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function create(array $payload, ?RefundSettings $refundSettings = null): array
    {
        $this->seedRefundRepository();

        $response = $this->createRoute($refundSettings)->create(
            new Request([], array_merge(['orderId' => self::ORDER_ID], $payload)),
            $this->context
        );

        return $this->decode($response->getContent());
    }
}
