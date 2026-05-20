<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Subscriber;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusCancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Action\CancelAction;
use Mollie\Shopware\Component\Subscription\Action\ConfirmAction;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionStartedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PendingSubscriptionSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 0;

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        private readonly CancelAction $cancelAction,
        private readonly ConfirmAction $confirmAction,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WebhookStatusPaidEvent::class => ['onPaidWebhook', self::PRIORITY],
            WebhookStatusCancelledEvent::class => ['onCancelledWebhook', self::PRIORITY],
        ];
    }

    public function onCancelledWebhook(WebhookStatusCancelledEvent $event): void
    {
        $order = $event->getOrder();
        $subscriptions = $this->getSubscriptions($order);
        if ($subscriptions->count() === 0) {
            return;
        }

        $logData = [
            'orderNumber' => (string) $order->getOrderNumber(),
        ];
        $context = $event->getContext();

        foreach ($subscriptions as $subscriptionEntity) {
            $logData['subscriptionId'] = $subscriptionEntity->getId();
            $logData['subscriptionStatus'] = $subscriptionEntity->getStatus();

            if ($subscriptionEntity->getStatus() !== SubscriptionStatus::PENDING->value) {
                $this->logger->warning('Subscription is not pending, nothing to do', $logData);

                continue;
            }

            $this->logger->info('Payment was cancelled, cancel pending subscription', $logData);
            $this->cancelAction->cancelPending($subscriptionEntity, $context);
        }
    }

    public function onPaidWebhook(WebhookStatusPaidEvent $event): void
    {
        $order = $event->getOrder();
        $subscriptions = $this->getSubscriptions($order);
        $pendingSubscriptions = $subscriptions->filterByStatus(SubscriptionStatus::PENDING->value);
        if ($pendingSubscriptions->count() === 0) {
            return;
        }

        $payment = $event->getPayment();
        $orderNumber = (string) $order->getOrderNumber();
        $logData = [
            'orderNumber' => $orderNumber,
        ];

        $mollieCustomerId = $payment->getCustomerId();
        if ($mollieCustomerId === null) {
            $this->logger->error('Failed to get mollie customer id from payment', $logData);

            throw new \RuntimeException('Failed to get mollie customer id');
        }

        $customer = $order->getOrderCustomer()?->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            $this->logger->error('Failed to get order customer', $logData);

            throw new \RuntimeException('Customer not loaded for Order');
        }

        $currency = $order->getCurrency();
        if ($currency === null) {
            $this->logger->error('Currency is not set', $logData);

            throw new \RuntimeException('Currency is not set');
        }

        $mandateId = $payment->getMandateId();
        if ($mandateId === null) {
            $this->logger->error('Failed to get mollie mandate id from payment', $logData);

            throw new \RuntimeException('Failed to get mollie mandate id');
        }

        $context = $event->getContext();

        foreach ($pendingSubscriptions as $subscriptionEntity) {
            $logData['subscriptionId'] = $subscriptionEntity->getId();
            $this->logger->info('Confirming pending subscription', $logData);

            $this->confirmAction->confirm($subscriptionEntity, $currency, $mandateId, $mollieCustomerId, $orderNumber, $context);
            $this->eventDispatcher->dispatch(new SubscriptionStartedEvent($subscriptionEntity, $customer, $context));
        }
    }

    private function getSubscriptions(OrderEntity $order): SubscriptionCollection
    {
        if (! $this->settingsService->getSubscriptionSettings($order->getSalesChannelId())->isEnabled()) {
            return new SubscriptionCollection();
        }

        $subscriptionCollection = $order->getExtension('mollieSubscriptions');
        if (! $subscriptionCollection instanceof SubscriptionCollection) {
            return new SubscriptionCollection();
        }

        return $subscriptionCollection;
    }
}
