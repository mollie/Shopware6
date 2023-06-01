<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Types\OrderStatus;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CancelOrderSubscriber implements EventSubscriberInterface
{
    /**
     * These Shopware actions will automatically trigger
     * our cancellation (if enabled in the config).
     */
    public const AUTOMATIC_TRIGGER_ACTIONS = [
        StateMachineTransitionActions::ACTION_CANCEL
    ];

    /**
     * Cancellations are only done for these Mollie states.
     */
    public const ALLOWED_CANCELLABLE_MOLLIE_STATES = [
        OrderStatus::STATUS_CREATED,
        OrderStatus::STATUS_AUTHORIZED,
        OrderStatus::STATUS_SHIPPING
    ];


    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var MollieApiFactory
     */
    private $apiFactory;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param MollieApiFactory $apiFactory
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     * @param LoggerInterface $loggerService
     */
    public function __construct(MollieApiFactory $apiFactory, OrderService $orderService, SettingsService $settingsService, LoggerInterface $loggerService)
    {
        $this->orderService = $orderService;
        $this->apiFactory = $apiFactory;
        $this->settingsService = $settingsService;
        $this->logger = $loggerService;
    }


    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order.state_changed' => ['onOrderStateChanges']
        ];
    }


    /**
     * @param StateMachineStateChangeEvent $event
     * @return void
     */
    public function onOrderStateChanges(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $transitionName = $event->getTransition()->getTransitionName();

        try {
            # if we don't have at least one of our
            # actions that automatically trigger this feature, continue
            if (!in_array($transitionName, self::AUTOMATIC_TRIGGER_ACTIONS, true)) {
                return;
            }

            # get order and extract our Mollie Order ID
            $order = $this->orderService->getOrder($event->getTransition()->getEntityId(), $event->getContext());

            # -----------------------------------------------------------------------------------------------------------------------

            # check if we have activated this feature in our plugin configuration
            $settings = $this->settingsService->getSettings($order->getSalesChannelId());

            if (!$settings->isAutomaticCancellation()) {
                return;
            }

            # -----------------------------------------------------------------------------------------------------------------------

            $orderAttributes = new OrderAttributes($order);

            $mollieOrderId = $orderAttributes->getMollieOrderId();

            # if we don't have a Mollie Order ID continue
            # this can also happen for subscriptions where we only have a tr_xxx Transaction ID.
            # but cancellation only works on orders anyway
            if (empty($mollieOrderId)) {
                return;
            }

            # -----------------------------------------------------------------------------------------------------------------------

            $apiClient = $this->apiFactory->getClient($order->getSalesChannelId());

            $mollieOrder = $apiClient->orders->get($mollieOrderId);

            # check if the status of the Mollie order allows
            # a cancellation based on our whitelist.
            if (in_array($mollieOrder->status, self::ALLOWED_CANCELLABLE_MOLLIE_STATES, true)) {
                $this->logger->debug('Starting auto-cancellation of order: ' . $order->getOrderNumber() . ', ' . $mollieOrderId);

                $apiClient->orders->cancel($mollieOrderId);

                $this->logger->info('Auto-cancellation of order: ' . $order->getOrderNumber() . ', ' . $mollieOrderId . ' successfully executed after transition: ' . $transitionName);
            }
        } catch (ApiException $e) {
            $this->logger->error(
                'Error when executing auto-cancellation of an order after transition: ' . $transitionName,
                [
                    'error' => $e,
                ]
            );
        }
    }
}
