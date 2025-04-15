<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\ShipmentManager\ShipmentManager;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
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
     * @var EntityRepository
     */
    private $repoOrderTransactions;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param EntityRepository $repoOrderTransactions
     */
    public function __construct(SettingsService $settings, ShipmentManager $mollieShipment, OrderService $orderService, $repoOrderTransactions, LoggerInterface $logger)
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

    public function onOrderDeliveryChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $transitionName = $event->getTransition()->getTransitionName();

        if ($transitionName !== StateMachineTransitionActions::ACTION_SHIP) {
            return;
        }


        $orderDeliveryId = $event->getTransition()->getEntityId();

        try {
            $order = $this->orderService->getOrderByDeliveryId($orderDeliveryId, $event->getContext());

            // get the configuration of the sales channel from the order
            $configSalesChannel = $this->settings->getSettings($order->getSalesChannelId());

            // if we don't even configure automatic shipping
            // then don't even look into our order to find out if we should actually starts
            if (! $configSalesChannel->getAutomaticShipping()) {
                return;
            }


            $swTransaction = $this->getLatestOrderTransaction($order->getId(), $event->getContext());
            if (! $swTransaction) {
                throw new \Exception('Order ' . $order->getOrderNumber() . ' does not have transactions');
            }
            // verify if the customer really paid with Mollie in the end
            $paymentMethod = $swTransaction->getPaymentMethod();

            if (! $paymentMethod instanceof PaymentMethodEntity) {
                throw new \Exception('Transaction ' . $swTransaction->getId() . ' has no payment method!');
            }

            $paymentMethodAttributes = new PaymentMethodAttributes($paymentMethod);

            if (! $paymentMethodAttributes->isMolliePayment()) {
                // just skip it if it has been paid
                // with another payment provider
                // do NOT throw an error
                return;
            }

            $this->logger->info('Starting Shipment through Order Delivery Transition for order: ' . $order->getOrderNumber());

            $this->mollieShipment->shipOrderRest($order, null, $event->getContext());
        } catch (\Throwable $ex) {
            $this->logger->error('Failed to transfer delivery state to mollie: ' . $ex->getMessage(), ['exception' => $ex]);

            return;
        }
    }

    private function getLatestOrderTransaction(string $orderId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $orderId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('paymentMethod');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->repoOrderTransactions->search($criteria, $context);

        return $result->first();
    }
}
