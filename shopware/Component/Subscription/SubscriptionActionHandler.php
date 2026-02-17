<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Action\AbstractAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionActionEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class SubscriptionActionHandler
{
    /**
     * @var AbstractAction[]
     */
    private array $actions = [];

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     * @param iterable<AbstractAction> $actions
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[AutowireIterator('mollie.subscription.action')]
        iterable $actions,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
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
        $criteria = new Criteria([$subscriptionId]);
        $criteria->addAssociation('historyEntries');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->getAssociation('order')->setLimit(1);
        $criteria->setLimit(1);
        $searchResult = $this->subscriptionRepository->search($criteria, $context);

        $this->logger->debug('Subscription Action Handler called', $logData);

        $subscriptionEntity = $searchResult->first();
        if (! $subscriptionEntity instanceof SubscriptionEntity) {
            $this->logger->error('Subscription was not found', $logData);
            throw new SubscriptionNotFoundException($subscriptionId);
        }
        $mollieSubscriptionId = $subscriptionEntity->getMollieId();
        $mollieCustomerId = $subscriptionEntity->getMollieCustomerId();
        $salesChannelId = $subscriptionEntity->getSalesChannelId();

        $logData = array_merge($logData, [
            'mollieSubscriptionId' => $mollieSubscriptionId,
            'mollieCustomerId' => $mollieCustomerId,
            'salesChannelId' => $salesChannelId,
        ]);

        $order = $subscriptionEntity->getOrder();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('Subscription is without order', $logData);
            throw new \Exception('Order was not found');
        }
        $orderNumber = (string) $order->getOrderNumber();
        $logData['orderNumber'] = $orderNumber;
        $this->logger->info('Subscription Action Handler - Started',$logData);
        $customer = $order->getOrderCustomer()?->getCustomer();

        if (! $customer instanceof CustomerEntity) {
            $this->logger->error('Subscription Action Handler - Subscription is without customer', $logData);
            throw new \Exception('Customer was not found');
        }

        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($subscriptionEntity->getSalesChannelId());
        if (! $subscriptionSettings->isEnabled()) {
            $this->logger->error('Subscription Action Handler - Failed to execute subscription action, subscriptions disabled for saleschannel', $logData);
            throw new SubscriptionsDisabledException();
        }

        $mollieSubscription = $this->subscriptionGateway->getSubscription($mollieSubscriptionId, $mollieCustomerId, $orderNumber, $salesChannelId);

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

        $mollieSubscription = $foundAction->execute($subscriptionEntity, $subscriptionSettings, $mollieSubscription, $orderNumber, $context);

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
