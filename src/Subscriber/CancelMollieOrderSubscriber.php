<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\OrderService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Types\OrderStatus;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CancelMollieOrderSubscriber implements EventSubscriberInterface
{
    public const MOLLIE_CANCEL_ORDER_STATES = [
        OrderStatus::STATUS_CREATED,
        OrderStatus::STATUS_AUTHORIZED,
        OrderStatus::STATUS_SHIPPING
    ];
    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var MollieApiFactory
     */
    private $apiFactory;


    /**
     * @param MollieApiFactory $apiFactory
     * @param OrderService $orderService
     * @param LoggerInterface $loggerService
     * @param string $shopwareVersion
     */
    public function __construct(MollieApiFactory $apiFactory, OrderService $orderService, LoggerInterface $loggerService, string $shopwareVersion)
    {
        $this->orderService = $orderService;
        $this->shopwareVersion = $shopwareVersion;
        $this->logger = $loggerService;
        $this->apiFactory = $apiFactory;
    }

    public static function getSubscribedEvents()
    {
        return [
            'state_machine.order.state_changed' => ['onOrderStateChanges']
        ];
    }

    public function onOrderStateChanges(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $allowedStates = [StateMachineTransitionActions::ACTION_CANCEL => true];

        if (version_compare($this->shopwareVersion, '6.2', '>=')) {
            $allowedStates[StateMachineTransitionActions::ACTION_FAIL] = true;
        }

        $transitionName = $event->getTransition()->getTransitionName();

        if (!isset($allowedStates[$transitionName])) {
            return;
        }

        $order = $this->orderService->getOrder($event->getTransition()->getEntityId(), $event->getContext());

        $customFields = $order->getCustomFields() ?? [];

        $mollieOrderId = $customFields['mollie_payments']['order_id'] ?? '';

        if (empty($mollieOrderId)) {
            return;
        }

        try {
            $apiClient = $this->apiFactory->getClient($order->getSalesChannelId());

            $mollieOrder = $apiClient->orders->get($mollieOrderId);

            if (in_array($mollieOrder->status, [self::MOLLIE_CANCEL_ORDER_STATES])) {
                $apiClient->orders->cancel($mollieOrderId);
            }
        } catch (ApiException $e) {
            $this->logger->warning(
                $e->getMessage()
            );
        }
    }
}
