<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent;
use Mollie\Shopware\Component\FlowBuilder\WebhookStatusEventFactory;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Handler\CompatibilityPaymentHandler;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

final class WebhookRoute extends AbstractWebhookRoute
{
    public function __construct(
        private MollieGatewayInterface $mollieGateway,
        private OrderTransactionStateHandler $stateMachineHandler,
        private WebhookStatusEventFactory $webhookStatusEventFactory,
        private EventDispatcherInterface $eventDispatcher,
        private ContainerInterface $container,
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    public function notify(Request $request, Context $context): WebhookRouteResponse
    {
        $transactionId = $request->get('transactionId');
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);

        $shopwareOrder = $payment->getShopwareTransaction()->getOrder();

        $webhookEvent = new WebhookEvent($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookEvent);

        $webhookStatusEvent = $this->webhookStatusEventFactory->create($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookStatusEvent);

        $this->updatePaymentStatus($payment, $transactionId, $context);
        $this->updatePaymentMethod($payment);

        // TODO: update order status
        return new WebhookRouteResponse();
    }

    private function updatePaymentStatus(Payment $payment, string $transactionId, Context $context): void
    {
        $shopwareHandlerMethod = $payment->getStatus()->getShopwareHandlerMethod();
        if (mb_strlen($shopwareHandlerMethod) === 0) {
            return;
        }

        $this->stateMachineHandler->{$shopwareHandlerMethod}($transactionId, $context);
    }

    private function updatePaymentMethod(Payment $payment): void
    {
        $transaction = $payment->getShopwareTransaction();
        $paymentHandlerIdentifier = $transaction->getPaymentMethod()->getHandlerIdentifier();

        /** @var CompatibilityPaymentHandler $paymentHandler */
        $paymentHandler = $this->container->get($paymentHandlerIdentifier);
        if ($paymentHandler->getPaymentMethod() === $payment->getMethod()) {
            return;
        }
        /** Apple Pay payment is stored as credit card on mollie side, so we do not want to switch payment method */
        if ($paymentHandler->getPaymentMethod() === 'applepay' && $payment->getMethod() === 'creditcard') {
            return;
        }
        // TODO: update payment method
    }
}
