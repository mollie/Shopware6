<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Route;

use Mollie\Shopware\Component\Refund\Route\CancelRefundRoute;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cancelling a refund at Mollie and cleaning the refund id off the payment extension.
 */
#[CoversClass(CancelRefundRoute::class)]
final class CancelRefundRouteTest extends RefundRouteTestCase
{
    public function testCancelPassesThePaymentAndTheRefundToMollie(): void
    {
        $this->givenOrder();

        $this->cancel('re_1');

        $this->assertSame(
            [['paymentId' => 'tr_1', 'refundId' => 're_1']],
            $this->refundGateway->getCancelledRefunds()
        );
    }

    /**
     * The refund is gone at Mollie, so its id must not stay in the export data of the order.
     */
    public function testCancelRemovesTheRefundIdFromThePaymentExtension(): void
    {
        $molliePayment = $this->molliePayment();
        $molliePayment->setRefundIds(['re_1', 're_2']);

        $this->givenOrder(molliePayment: $molliePayment);

        $this->cancel('re_1');

        $saved = $this->transactionService->getSavedPaymentExtensions();
        $this->assertSame(['re_2'], $saved[0]['payment']->getRefundIds());
    }

    public function testCancelReportsSuccessWithTheRecalculatedTotals(): void
    {
        $this->givenOrder();

        $body = $this->cancel('re_1');

        $this->assertTrue($body['success']);
        $this->assertSame(self::REFUNDABLE_TOTAL, (float) $body['totals']['remaining']);
    }

    public function testCancelFailsWithoutAMolliePaymentOnTheTransaction(): void
    {
        $this->givenOrder(molliePayment: null);

        $this->expectExceptionMessage('No Mollie payment extension found for order "order-1"');

        $this->cancel('re_1');
    }

    /**
     * @return array<string, mixed>
     */
    private function cancel(string $refundId): array
    {
        // No credit note line item exists for the refund, so the cancel leaves the order alone.
        $this->creditNoteLineItemRepository->idSearchResults[] = new IdSearchResult(0, [], new Criteria(), $this->context);

        $response = $this->cancelRoute()->cancel(
            new Request([], ['orderId' => self::ORDER_ID, 'refundId' => $refundId]),
            $this->context
        );

        return $this->decode($response->getContent());
    }
}
