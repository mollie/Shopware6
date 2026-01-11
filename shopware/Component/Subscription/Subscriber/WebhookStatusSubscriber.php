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
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Event\ModifyCreateSubscriptionPayloadEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class WebhookStatusSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 0;

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService      $settingsService,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface                 $routeBuilder,
        #[Autowire(service: 'mollie_subscription.repository')]
        private EntityRepository                      $subscriptionRepository,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface              $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface              $logger
    )
    {
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
        $payment = $event->getPayment();
        $salesChannelId = $order->getSalesChannelId();

        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelId);
        if (!$subscriptionSettings->isEnabled()) {
            return;
        }

        $subscriptionCollection = $order->getExtension('subscription');
        if (!$subscriptionCollection instanceof SubscriptionCollection) {
            return;
        }

        $firstSubscription = $subscriptionCollection->first();
        if (!$firstSubscription instanceof SubscriptionEntity) {
            return;
        }

        $subscriptionId = $firstSubscription->getId();
        $subscriptionStatus = $firstSubscription->getStatus();
        $orderNumber = (string)$order->getOrderNumber();

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

        $newSubscriptionStatus = SubscriptionStatus::CANCELED->value;
        $subscriptionData = [
            'id' => $subscriptionId,
            'status' => $newSubscriptionStatus,
            'mollieCustomerId' => $mollieCustomerId,
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
        $salesChannelId = $order->getSalesChannelId();

        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelId);
        if (!$subscriptionSettings->isEnabled()) {
            return;
        }

        $subscriptionCollection = $order->getExtension('subscription');
        if (!$subscriptionCollection instanceof SubscriptionCollection) {
            return;
        }

        $firstSubscription = $subscriptionCollection->first();
        if (!$firstSubscription instanceof SubscriptionEntity) {
            return;
        }

        $subscriptionId = $firstSubscription->getId();
        $subscriptionStatus = $firstSubscription->getStatus();
        $orderNumber = (string)$order->getOrderNumber();

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

        $currency = $order->getCurrency();
        if ($currency === null) {
            $this->logger->warning('Currency is not set', $logData);
            throw new \Exception('Currency is not set');
        }

        $mollieCustomerId = $payment->getCustomerId();

        if ($mollieCustomerId === null) {
            $this->logger->error('Failed to get mollie customer id from payment', $logData);
            throw new \Exception('Failed to get mollie customer id');
        }

        $mandateId = $payment->getMandateId();

        if ($mandateId === null) {
            $this->logger->error('Failed to get mollie mandate id from payment', $logData);
            throw new \Exception('Failed to get mollie mandate id');
        }

        $metaData = $firstSubscription->getMetadata();

        $createSubscription = new CreateSubscription(
            $firstSubscription->getDescription(),
            $metaData->getInterval(),
            new Money($order->getAmountTotal(), $currency->getIsoCode())
        );
        $createSubscription->setWebhookUrl($this->routeBuilder->getSubscriptionWebhookUrl($subscriptionId));
        $createSubscription->setMandateId($mandateId);
        $createSubscription->setStartDate($metaData->getStartDate());

        $createSubscription->setMetadata([
            'subscriptionId' => $subscriptionId,
        ]);
        $repetition = (int)$metaData->getTimes();

        if ($repetition > 0) {
            $createSubscription->setTimes($repetition);
        }
        $context = $event->getContext();
        /** @var ModifyCreateSubscriptionPayloadEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyCreateSubscriptionPayloadEvent($createSubscription, $context));
        $createSubscription = $event->getCreateSubscription();

        $subscription = $this->subscriptionGateway->createSubscription($createSubscription, $mollieCustomerId, $orderNumber, $salesChannelId);
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
    }
}
