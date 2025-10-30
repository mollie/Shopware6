<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Payment\Handler\CompatibilityPaymentHandler;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

final class WebhookRoute extends AbstractWebhookRoute
{
    public function __construct(
        private MollieGatewayInterface $mollieGateway,
        private OrderTransactionStateHandler $stateMachineHandler,
        private ContainerInterface $container,
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    public function notify(string $transactionId, Context $context): WebhookRouteResponse
    {
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);

        $shopwareHandlerMethod = $payment->getStatus()->getShopwareHandlerMethod();
        if (mb_strlen($shopwareHandlerMethod) > 0) {
            $this->stateMachineHandler->{$shopwareHandlerMethod}($transactionId, $context);
        }

        $transaction = $payment->getShopwareTransaction();
        $paymentHandlerIdentifier = $transaction->getPaymentMethod()->getHandlerIdentifier();
        /** @var CompatibilityPaymentHandler $paymentHandler */
        $paymentHandler = $this->container->get($paymentHandlerIdentifier);
        if ($paymentHandler->getPaymentMethodName() === $payment->getMethod()) {
            return new WebhookRouteResponse();
        }
        //TODO change payment method, but only if it snot apple pay
    }
}
