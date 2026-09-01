<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Return;

use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\Route\AbstractCancelRefundRoute;
use Mollie\Shopware\Component\Refund\Route\CancelRefundRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cancels the Mollie refund that belongs to a return the merchant withdrew. Mollie is asked for its
 * refunds and the matching one is found by the returnId stored in its metadata, because the plugin
 * does not keep that mapping itself.
 *
 * Not final: OrderReturnSubscriber is unit tested against a subclass of this action.
 */
class CancelAction extends AbstractReturnAction
{
    public function __construct(
        #[Autowire(service: CancelRefundRoute::class)]
        private readonly AbstractCancelRefundRoute $cancelRefundRoute,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
        #[Autowire(service: OrderReturnLoader::class)]
        OrderReturnLoaderInterface $orderReturnLoader,
        #[Autowire(service: SettingsService::class)]
        AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        LoggerInterface $logger,
    ) {
        parent::__construct($orderReturnLoader, $settingsService, $logger);
    }

    public function execute(string $returnId, Context $context): void
    {
        $orderReturn = $this->loadReturn($returnId, $context);
        if ($orderReturn === null) {
            return;
        }

        $order = $this->resolveOrder($orderReturn);
        if ($order === null) {
            return;
        }

        $logData = $this->buildLogData($orderReturn, $order);
        $this->logger->info('OrderReturn - Refund cancellation triggered', $logData);

        $payment = $this->extractMolliePayment($order);
        if ($payment === null) {
            $this->logger->warning('OrderReturn - No Mollie payment found, skipping cancellation', $logData);

            return;
        }

        $logData['paymentId'] = $payment->getId();

        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = (string) $order->getSalesChannelId();

        $this->logger->info('OrderReturn - Listing Mollie refunds to find matching return', $logData);

        try {
            $refunds = $this->refundGateway->listRefunds($payment->getId(), $orderNumber, $salesChannelId);
            $refund = $refunds->findByReturnId($returnId);

            if ($refund === null) {
                $this->logger->warning('OrderReturn - No matching Mollie refund found for returnId', $logData);

                return;
            }

            $logData['refundId'] = $refund->getId();
            $this->logger->info('OrderReturn - Found matching Mollie refund, cancelling', $logData);

            $request = new Request([], [
                'orderId' => $order->getId(),
                'refundId' => $refund->getId(),
            ]);

            $this->cancelRefundRoute->cancel($request, $this->liveContext($context));

            $this->logger->info('OrderReturn - Refund cancelled successfully', $logData);
        } catch (\Throwable $e) {
            $logData['error'] = $e->getMessage();
            $this->logger->error('OrderReturn - Refund cancellation failed', $logData);
        }
    }

    private function extractMolliePayment(OrderEntity $order): ?Payment
    {
        $transactions = new MollieOrderTransactionCollection($order->getTransactions());
        $transaction = $transactions->getCurrentOrderTransaction();
        if ($transaction === null) {
            return null;
        }

        $payment = $transaction->getExtension(Mollie::EXTENSION);

        return $payment instanceof Payment ? $payment : null;
    }
}
