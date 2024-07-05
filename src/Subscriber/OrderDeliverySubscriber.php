<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\ShipmentManager\ShipmentManager;
use Kiener\MolliePayments\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDeliverySubscriber implements EventSubscriberInterface
{
    /**
     * @var SettingsService
     */
    private $settings;

    /**
     * @var ShipmentManager
     */
    private $mollieShipment;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var OrderTransactionRepositoryInterface
     */
    private $repoOrderTransactions;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SettingsService $settings
     * @param ShipmentManager $mollieShipment
     * @param OrderService $orderService
     * @param OrderTransactionRepositoryInterface $repoOrderTransactions
     * @param LoggerInterface $logger
     */
    public function __construct(SettingsService $settings, ShipmentManager $mollieShipment, OrderService $orderService, OrderTransactionRepositoryInterface $repoOrderTransactions, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->mollieShipment = $mollieShipment;
        $this->orderService = $orderService;
        $this->repoOrderTransactions = $repoOrderTransactions;
        $this->logger = $logger;
    }


    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryChanged',
        ];
    }

    /**
     * @param StateMachineStateChangeEvent $event
     */
    public function onOrderDeliveryChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $transitionName = $event->getTransition()->getTransitionName();

        if ($transitionName !== StateMachineTransitionActions::ACTION_SHIP) {
            return;
        }

        # get the configuration of the sales channel from the order
        $configSalesChannel = $this->settings->getSettings($event->getSalesChannelId());


        # if we don't even configure automatic shipping
        # then don't even look into our order to find out if we should actually starts
        if (!$configSalesChannel->getAutomaticShipping()) {
            return;
        }

        $orderDeliveryId = $event->getTransition()->getEntityId();

        try {
            $order = $this->orderService->getOrderByDeliveryId($orderDeliveryId, $event->getContext());

            $swTransaction = $this->repoOrderTransactions->getLatestOrderTransaction($order->getId(), $event->getContext());

            # verify if the customer really paid with Mollie in the end
            $paymentMethod = $swTransaction->getPaymentMethod();

            if (!$paymentMethod instanceof PaymentMethodEntity) {
                throw new \Exception('Transaction ' . $swTransaction->getId() . ' has no payment method!');
            }

            $paymentMethodAttributes = new PaymentMethodAttributes($paymentMethod);

            if (!$paymentMethodAttributes->isMolliePayment()) {
                # just skip it if it has been paid
                # with another payment provider
                # do NOT throw an error
                return;
            }

            $this->logger->info('Starting Shipment through Order Delivery Transition for order: ' . $order->getOrderNumber());

            $this->mollieShipment->shipOrderRest($order, null, $event->getContext());
        } catch (\Throwable $ex) {
            $this->logger->error('Failed to transfer delivery state to mollie: '.$ex->getMessage());
            return;
        }
    }
}
