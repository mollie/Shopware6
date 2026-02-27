<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Action\AbstractAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionActionEvent;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionDisabledException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class SubscriptionActionHandler
{
    /**
     * @var AbstractAction[]
     */
    private array $actions = [];

    /**
     * @param iterable<AbstractAction> $actions
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: SubscriptionDataService::class)]
        private readonly SubscriptionDataServiceInterface $subscriptionDataService,
        #[AutowireIterator('mollie.subscription.action')]
        iterable $actions,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
        foreach ($actions as $action) {
            $this->registerAction($action);
        }
    }

    public function handle(string $action, string $subscriptionId, Context $context): Subscription
    {
        $logData = [
            'subscriptionId' => $subscriptionId,
            'action' => $action,
        ];

        $subscriptionId = strtolower($subscriptionId);
        $this->logger->debug('Subscription Action Handler called', $logData);

        $subscriptionData = $this->subscriptionDataService->findById($subscriptionId, $context);

        $order = $subscriptionData->getOrder();

        $subscriptionEntity = $subscriptionData->getSubscription();
        $mollieSubscriptionId = $subscriptionEntity->getMollieId();
        $mollieCustomerId = $subscriptionEntity->getMollieCustomerId();
        $salesChannelId = $subscriptionEntity->getSalesChannelId();
        $orderNumber = (string) $order->getOrderNumber();
        $customer = $subscriptionData->getCustomer();

        $logData = array_merge($logData, [
            'mollieSubscriptionId' => $mollieSubscriptionId,
            'mollieCustomerId' => $mollieCustomerId,
            'salesChannelId' => $salesChannelId,
            'orderNumber' => $orderNumber,
        ]);

        $this->logger->info('Subscription Action Handler - Started', $logData);

        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelId);
        if (! $subscriptionSettings->isEnabled()) {
            $this->logger->error('Subscription Action Handler - Failed to execute subscription action, subscriptions disabled for saleschannel', $logData);
            throw new SubscriptionDisabledException($salesChannelId);
        }

        $foundAction = null;

        foreach ($this->actions as $actionHandler) {
            if ($actionHandler->supports($action)) {
                $foundAction = $actionHandler;
                break;
            }
        }

        if ($foundAction === null) {
            $this->logger->error('Subscription Action Handler - No action handler found for action', $logData);
            throw new \Exception('No action handler found for action: ' . $action);
        }

        $mollieSubscription = $this->subscriptionGateway->getSubscription($mollieSubscriptionId, $mollieCustomerId, $orderNumber, $salesChannelId);
        $mollieSubscription = $foundAction->execute($subscriptionData, $subscriptionSettings, $mollieSubscription, $orderNumber, $context);

        $event = $foundAction->getEvent($subscriptionEntity, $customer, $context);
        $logData['event'] = get_class($event);

        $this->eventDispatcher->dispatch($event);

        $this->logger->info('Subscription Action Handler - Finished', $logData);

        return $mollieSubscription;
    }

    /**
     * @return array<class-string<SubscriptionActionEvent>>
     */
    public function getActionEvents(): array
    {
        $result = [];
        foreach ($this->actions as $action) {
            $result[] = $action->getEventClass();
        }

        return $result;
    }

    private function registerAction(AbstractAction $action): void
    {
        $this->actions[] = $action;
    }
}
