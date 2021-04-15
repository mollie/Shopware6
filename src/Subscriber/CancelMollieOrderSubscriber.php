<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\OrderService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Monolog\Logger;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CancelMollieOrderSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $shopwareVersion;
    /**
     * @var MollieApiClient
     */
    private $apiClient;
    /**
     * @var OrderService
     */
    private $orderService;
    /**
     * @var LoggerService
     */
    private $loggerService;

    public function __construct(
        MollieApiClient $apiClient,
        OrderService $orderService,
        LoggerService $loggerService,
        string $shopwareVersion
    )
    {
        $this->apiClient = $apiClient;
        $this->orderService = $orderService;
        $this->shopwareVersion = $shopwareVersion;
        $this->loggerService = $loggerService;
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
            $mollieOrder = $this->apiClient->orders->get($mollieOrderId);

            if (in_array($mollieOrder->status, ['created', 'authorized', 'shipping'])) {
                $this->apiClient->orders->cancel($mollieOrderId);
            }
        } catch (ApiException $e) {
            $this->loggerService->addEntry(
                $e->getMessage(),
                $event->getContext(),
                $e,
                null,
                Logger::WARNING
            );
        }

    }
}
