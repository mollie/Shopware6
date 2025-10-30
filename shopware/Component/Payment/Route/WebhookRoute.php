<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class WebhookRoute extends AbstractWebhookRoute
{
    public function __construct(
        private MollieGatewayInterface $mollieGateway,
        private OrderTransactionStateHandler $stateMachineHandler,
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    public function notify(string $transactionId, SalesChannelContext $salesChannelContext): WebhookRouteResponse
    {
        $context = $salesChannelContext->getContext();

        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);

        $shopwareHandlerMethod = $payment->getStatus()->getShopwareHandlerMethod();
        if (mb_strlen($shopwareHandlerMethod) > 0) {
            $this->stateMachineHandler->{$shopwareHandlerMethod}($transactionId, $context);
        }


    }
}
