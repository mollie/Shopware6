<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Subscriber;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusCancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Event\ModifyCreateSubscriptionPayloadEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionStartedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PendingSubscriptionSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 0;

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        #[Autowire(service: 'mollie_subscription.repository')]
        private EntityRepository $subscriptionRepository,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
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

        $subscriptionEntity = $this->getSubscription($order);
        if (! $subscriptionEntity instanceof SubscriptionEntity) {
            return;
        }

        $subscriptionId = $subscriptionEntity->getId();
        $subscriptionStatus = $subscriptionEntity->getStatus();
        $orderNumber = (string) $order->getOrderNumber();

        $logData = [
            'orderNumber' => $orderNumber,
            'subscriptionId' => $subscriptionId,
            'subscriptionStatus' => $subscriptionStatus
        ];

        $this->logger->info('Payment was cancelled, cancel subscription', $logData);

        if ($subscriptionStatus !== SubscriptionStatus::PENDING->value) {
            $this->logger->warning('Subscription is not pending, nothing to do', $logData);

            return;
        }

        $newSubscriptionStatus = SubscriptionStatus::CANCELED->value;
        $subscriptionData = [
            'id' => $subscriptionId,
            'status' => $newSubscriptionStatus,
            'canceledAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            'historyEntries' => [
                [
                    'statusFrom' => $subscriptionStatus,
                    'statusTo' => $newSubscriptionStatus,
                    'comment' => 'canceled'
                ]
            ]
        ];

        $context = $event->getContext();

        $this->subscriptionRepository->upsert([$subscriptionData], $context);
    }

    public function onPaidWebhook(WebhookStatusPaidEvent $event): void
    {
        $order = $event->getOrder();
        $payment = $event->getPayment();

        $subscriptionEntity = $this->getSubscription($order);
        if (! $subscriptionEntity instanceof SubscriptionEntity) {
            return;
        }

        $subscriptionId = $subscriptionEntity->getId();
        $subscriptionStatus = $subscriptionEntity->getStatus();
        $orderNumber = (string) $order->getOrderNumber();

        $logData = [
            'orderNumber' => $orderNumber,
            'subscriptionId' => $subscriptionId,
            'subscriptionStatus' => $subscriptionStatus
        ];

        $this->logger->info('Start finalize subscription', $logData);

        if ($subscriptionStatus !== SubscriptionStatus::PENDING->value) {
            $this->logger->warning('Subscription is not pending, nothing to do', $logData);

            return;
        }

        $mollieCustomerId = $payment->getCustomerId();

        if ($mollieCustomerId === null) {
            $this->logger->error('Failed to get mollie customer id from payment', $logData);
            throw new \Exception('Failed to get mollie customer id');
        }

        $customer = $order->getOrderCustomer()?->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            $this->logger->error('Failed to get order customer', $logData);
            throw new \Exception('Customer not loaded for Order');
        }

        $currency = $order->getCurrency();
        if ($currency === null) {
            $this->logger->error('Currency is not set', $logData);
            throw new \Exception('Currency is not set');
        }

        $mandateId = $payment->getMandateId();

        if ($mandateId === null) {
            $this->logger->error('Failed to get mollie mandate id from payment', $logData);
            throw new \Exception('Failed to get mollie mandate id');
        }

        $context = $event->getContext();
        $subscription = $this->createSubscription($subscriptionEntity, $order, $currency, $mandateId, $logData, $mollieCustomerId, $context);

        $newSubscriptionStatus = $subscription->getStatus()->value;

        $subscriptionData = [
            'id' => $subscriptionId,
            'status' => $subscription->getStatus()->value,
            'mollieId' => $subscription->getId(),
            'mollieCustomerId' => $mollieCustomerId,
            'mandateId' => $mandateId,
            'nextPaymentAt' => $subscription->getNextPaymentDate()->format('Y-m-d'),
            'canceledAt' => null,
            'historyEntries' => [
                [
                    'statusFrom' => $subscriptionStatus,
                    'statusTo' => $newSubscriptionStatus,
                    'mollieId' => $subscription->getId(),
                    'comment' => 'confirmed'
                ]
            ]
        ];

        $this->subscriptionRepository->upsert([$subscriptionData], $context);
        $startedEvent = new SubscriptionStartedEvent($subscriptionEntity, $customer, $context);
        $this->eventDispatcher->dispatch($startedEvent);
    }

    /**
     * @param array<mixed> $logData
     */
    private function createSubscription(SubscriptionEntity $subscriptionEntity, OrderEntity $order, CurrencyEntity $currency, string $mandateId, array $logData, string $mollieCustomerId, Context $context): Subscription
    {
        $metaData = $subscriptionEntity->getMetadata();
        $subscriptionId = $subscriptionEntity->getId();

        $createSubscription = new CreateSubscription(
            $subscriptionEntity->getDescription(),
            $metaData->getInterval(),
            new Money($order->getAmountTotal(), $currency->getIsoCode())
        );

        $createSubscription->setWebhookUrl($this->routeBuilder->getSubscriptionWebhookUrl($subscriptionId));
        $createSubscription->setMandateId($mandateId);
        $createSubscription->setStartDate($metaData->getStartDate());

        $createSubscription->setMetadata([
            'subscriptionId' => $subscriptionId,
        ]);

        $repetition = (int) $metaData->getTimes();
        if ($repetition > 0) {
            $createSubscription->setTimes($repetition);
        }

        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();

        /** @var ModifyCreateSubscriptionPayloadEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyCreateSubscriptionPayloadEvent($createSubscription, $context));
        $createSubscription = $event->getCreateSubscription();
        $logData['payload'] = $createSubscription->toArray();

        $this->logger->info('Send create subscription payload to mollie API', $logData);

        return $this->subscriptionGateway->createSubscription($createSubscription, $mollieCustomerId, $orderNumber, $salesChannelId);
    }

    private function getSubscription(OrderEntity $order): ?SubscriptionEntity
    {
        $salesChannelId = $order->getSalesChannelId();
        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelId);
        if (! $subscriptionSettings->isEnabled()) {
            return null;
        }

        $subscriptionCollection = $order->getExtension('subscription');
        if (! $subscriptionCollection instanceof SubscriptionCollection) {
            return null;
        }

        return $subscriptionCollection->first();
    }
}
