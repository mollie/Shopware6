<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\ModifyCreateSubscriptionPayloadEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ConfirmAction
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: RouteBuilder::class)]
        private readonly RouteBuilderInterface $routeBuilder,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function confirm(
        SubscriptionEntity $subscription,
        CurrencyEntity $currency,
        string $mandateId,
        string $mollieCustomerId,
        string $orderNumber,
        Context $context
    ): Subscription {
        $subscriptionId = $subscription->getId();
        $statusFrom = $subscription->getStatus();
        $logData = [
            'subscriptionId' => $subscriptionId,
            'orderNumber' => $orderNumber,
            'subscriptionStatus' => $statusFrom,
        ];

        $this->logger->info('Confirming pending subscription via Mollie API', $logData);

        $mollieSubscription = $this->createMollieSubscription($subscription, $currency, $mandateId, $mollieCustomerId, $orderNumber, $context, $logData);
        $nextPaymentDate = $mollieSubscription->getNextPaymentDate();
        if (! $nextPaymentDate instanceof \DateTimeInterface) {
            $this->logger->error('Confirmed Mollie subscription has no next payment date', $logData);

            throw new \RuntimeException(sprintf('Confirmed Mollie subscription "%s" has no next payment date', $mollieSubscription->getId()));
        }

        $newStatus = $mollieSubscription->getStatus()->value;

        $this->subscriptionRepository->upsert([[
            'id' => $subscriptionId,
            'status' => $newStatus,
            'mollieId' => $mollieSubscription->getId(),
            'mollieCustomerId' => $mollieCustomerId,
            'mandateId' => $mandateId,
            'nextPaymentAt' => $nextPaymentDate->format('Y-m-d'),
            'canceledAt' => null,
            'historyEntries' => [[
                'statusFrom' => $statusFrom,
                'statusTo' => $newStatus,
                'mollieId' => $mollieSubscription->getId(),
                'comment' => 'confirmed',
            ]],
        ]], $context);

        return $mollieSubscription;
    }

    /**
     * @param array<mixed> $logData
     */
    private function createMollieSubscription(
        SubscriptionEntity $subscription,
        CurrencyEntity $currency,
        string $mandateId,
        string $mollieCustomerId,
        string $orderNumber,
        Context $context,
        array $logData
    ): Subscription {
        $metaData = $subscription->getMetadata();

        $createSubscription = new CreateSubscription(
            $subscription->getDescription(),
            $metaData->getInterval(),
            new Money($subscription->getAmount(), $currency->getIsoCode())
        );

        $createSubscription->setWebhookUrl($this->routeBuilder->getSubscriptionWebhookUrl($subscription->getId()));
        $createSubscription->setMandateId($mandateId);
        $createSubscription->setStartDate($metaData->getStartDate());
        $createSubscription->setMetadata([
            'subscriptionId' => $subscription->getId(),
        ]);

        $repetition = (int) $metaData->getTimes();
        if ($repetition > 0) {
            $createSubscription->setTimes($repetition);
        }

        /** @var ModifyCreateSubscriptionPayloadEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyCreateSubscriptionPayloadEvent($createSubscription, $context));
        $createSubscription = $event->getCreateSubscription();
        $logData['payload'] = $createSubscription->toArray();

        $this->logger->info('Send create subscription payload to Mollie API', $logData);

        return $this->subscriptionGateway->createSubscription($createSubscription, $mollieCustomerId, $orderNumber, $subscription->getSalesChannelId());
    }
}
