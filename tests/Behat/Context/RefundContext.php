<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Step\Then;
use Behat\Step\When;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\Controller\RefundController;
use Mollie\Shopware\Integration\Data\CheckoutTestBehaviour;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

final class RefundContext extends ShopwareContext
{
    use CheckoutTestBehaviour;

    private const STORAGE_LAST_REFUND_RESPONSE = 'lastRefundResponse';
    private const STORAGE_LAST_REFUND_ID = 'lastRefundId';
    private const STORAGE_REFUND_EXCEPTION = 'refundException';

    #[When('i create a full refund')]
    public function iCreateAFullRefund(): void
    {
        $this->callCreateRefundRoute(['orderId' => Storage::get(CheckoutContext::STORAGE_ORDER_ID)]);
    }

    #[When('i refund line item :productNumber with quantity :quantity')]
    public function iRefundLineItemWithQuantity(string $productNumber, int $quantity): void
    {
        $this->callRefundLineItem($productNumber, $quantity);
    }

    #[When('i refund line item :productNumber with quantity :quantity and amount :amount')]
    public function iRefundLineItemWithQuantityAndAmount(string $productNumber, int $quantity, string $amount): void
    {
        $this->callRefundLineItem($productNumber, $quantity, (float) $amount);
    }

    #[When('i refund line item :productNumber with partial amount :amount')]
    public function iRefundLineItemWithPartialAmount(string $productNumber, string $amount): void
    {
        $orderId = Storage::get(CheckoutContext::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();

        $order = $this->getOrderById($orderId, $salesChannelContext);
        $lineItems = $order->getLineItems();
        Assert::assertNotNull($lineItems, 'Order has no line items');

        $lineItem = null;
        foreach ($lineItems as $item) {
            if ($item->getProduct()?->getProductNumber() === $productNumber) {
                $lineItem = $item;
                break;
            }
        }

        Assert::assertNotNull($lineItem, sprintf('Line item with product number "%s" not found', $productNumber));

        $this->callCreateRefundRoute([
            'orderId' => $orderId,
            'amount' => (float) $amount,
            'items' => [['id' => $lineItem->getId(), 'quantity' => 0, 'amount' => (float) $amount]],
        ]);
    }

    #[When('i cancel the last refund')]
    public function iCancelTheLastRefund(): void
    {
        $orderId = Storage::get(CheckoutContext::STORAGE_ORDER_ID);
        $refundId = Storage::get(self::STORAGE_LAST_REFUND_ID);
        Assert::assertNotNull($refundId, 'No refund ID stored from previous refund');

        $context = $this->getCurrentSalesChannelContext()->getContext();

        /** @var RefundController $controller */
        $controller = $this->getContainer()->get(RefundController::class);

        $request = new Request();
        $request->request->replace(['orderId' => $orderId, 'refundId' => $refundId]);

        $controller->cancel($request, $context);
    }

    #[When('i refund the amount :amount')]
    public function iRefundTheAmount(string $amount): void
    {
        $this->callCreateRefundRoute([
            'orderId' => Storage::get(CheckoutContext::STORAGE_ORDER_ID),
            'amount' => (float) $amount,
        ]);
    }

    #[Then('there are :count pending refunds')]
    public function thereArePendingRefunds(int $count): void
    {
        $orderId = Storage::get(CheckoutContext::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $order = $this->getOrderById($orderId, $salesChannelContext);

        $transaction = $order->getTransactions()?->first();
        Assert::assertNotNull($transaction, 'Order has no transaction');

        $mollieExtension = $transaction->getExtension(Mollie::EXTENSION);
        Assert::assertInstanceOf(Payment::class, $mollieExtension, 'No Mollie payment extension found');

        /** @var RefundGatewayInterface $gateway */
        $gateway = $this->getContainer()->get(RefundGateway::class);
        $refunds = $gateway->listRefunds($mollieExtension->getId(), (string) $order->getSalesChannelId());

        $pendingCount = count(array_filter(
            $refunds->jsonSerialize(),
            function ($refund) {
                return $refund->getStatus() === RefundStatus::Pending || $refund->getStatus() === RefundStatus::Queued;
            }
        ));

        Assert::assertSame($count, $pendingCount, sprintf('Expected %d pending refunds but got %d', $count, $pendingCount));
    }

    #[Then('the refund amount is :expectedAmount')]
    public function theRefundAmountIs(string $expectedAmount): void
    {
        $exception = Storage::get(self::STORAGE_REFUND_EXCEPTION);
        Assert::assertNull($exception, sprintf('Refund failed with exception: %s', $exception));

        /** @var array<string, mixed> $response */
        $response = Storage::get(self::STORAGE_LAST_REFUND_RESPONSE);
        Assert::assertIsArray($response, 'No refund response was stored');
        Assert::assertSame($expectedAmount, $response['amount']['value'], sprintf(
            'Expected refund amount "%s" but got "%s"',
            $expectedAmount,
            $response['amount']['value'],
        ));
    }

    #[Then('the refund is created with status :expectedStatus')]
    public function theRefundIsCreatedWithStatus(string $expectedStatus): void
    {
        $exception = Storage::get(self::STORAGE_REFUND_EXCEPTION);
        Assert::assertNull($exception, sprintf('Refund failed with exception: %s', $exception));

        /** @var array<string, mixed> $response */
        $response = Storage::get(self::STORAGE_LAST_REFUND_RESPONSE);
        Assert::assertIsArray($response, 'No refund response was stored');
        Assert::assertSame($expectedStatus, $response['status'], sprintf(
            'Expected refund status "%s" but got "%s"',
            $expectedStatus,
            $response['status'],
        ));
    }

    private function callRefundLineItem(string $productNumber, int $quantity, ?float $amount = null): void
    {
        $orderId = Storage::get(CheckoutContext::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();

        $order = $this->getOrderById($orderId, $salesChannelContext);
        $lineItems = $order->getLineItems();
        Assert::assertNotNull($lineItems, 'Order has no line items');

        $lineItem = null;
        foreach ($lineItems as $item) {
            if ($item->getProduct()?->getProductNumber() === $productNumber) {
                $lineItem = $item;
                break;
            }
        }

        Assert::assertNotNull($lineItem, sprintf('Line item with product number "%s" not found', $productNumber));

        $item = ['id' => $lineItem->getId(), 'quantity' => $quantity];

        if ($amount !== null) {
            $item['amount'] = $amount;
        }

        $params = [
            'orderId' => $orderId,
            'items' => [$item],
        ];

        $this->callCreateRefundRoute($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function callCreateRefundRoute(array $params): void
    {
        $context = $this->getCurrentSalesChannelContext()->getContext();

        /** @var RefundController $controller */
        $controller = $this->getContainer()->get(RefundController::class);

        $request = new Request();
        $request->request->replace($params);

        Storage::set(self::STORAGE_LAST_REFUND_RESPONSE, null);
        Storage::set(self::STORAGE_REFUND_EXCEPTION, null);

        try {
            $response = $controller->create($request, $context);
            $data = json_decode((string) $response->getContent(), true);
            Storage::set(self::STORAGE_LAST_REFUND_RESPONSE, $data);
            if (isset($data['id'])) {
                Storage::set(self::STORAGE_LAST_REFUND_ID, $data['id']);
            }
        } catch (\Throwable $e) {
            Storage::set(self::STORAGE_REFUND_EXCEPTION, $e->getMessage());
        }
    }
}
