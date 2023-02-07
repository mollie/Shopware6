<?php


namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'state_machine.order.state_changed' => ['onKlarnaOrderCancelledAsAdmin']
        ];
    }

    /** @var MollieApiFactory */
    private $apiFactory;
    /** @var OrderService */
    private $orderService;

    /** @var PaymentMethodService */
    private $paymentMethodService;

    public function __construct(
        MollieApiFactory     $apiFactory,
        OrderService         $orderService,
        PaymentMethodService $paymentMethodService
    ) {
        $this->orderService = $orderService;
        $this->paymentMethodService = $paymentMethodService;
        $this->apiFactory = $apiFactory;
    }


    /**
     * @param StateMachineStateChangeEvent $event
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function onKlarnaOrderCancelledAsAdmin(StateMachineStateChangeEvent $event): void
    {
        if (! ($event->getContext()->getSource() instanceof AdminApiSource)) {
            return;
        }

        // Build order state change to cancelled event name
        $orderStateCancelled = implode('.', [
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            OrderStates::STATE_MACHINE,
            OrderStates::STATE_CANCELLED
        ]);

        if ($event->getStateEventName() !== $orderStateCancelled) {
            return;
        }


        $order = $this->orderService->getOrder($event->getTransition()->getEntityId(), $event->getContext());

        if (! $order instanceof OrderEntity) {
            return;
        }

        if (! $order->getTransactions() instanceof OrderTransactionCollection) {
            return;
        }

        // use filterByState(OrderTransactionStates::STATE_OPEN)?
        $lastTransaction = $order->getTransactions()->last();

        if (! $lastTransaction instanceof OrderTransactionEntity) {
            return;
        }

        $paymentMethod = $lastTransaction->getPaymentMethod();

        if (is_null($paymentMethod) && $lastTransaction->getPaymentMethodId() !== '') {
            $paymentMethod = $this->paymentMethodService->getPaymentMethodById($lastTransaction->getPaymentMethodId());
        }

        $molliePaymentMethod = null;

        if (! is_null($paymentMethod) && ! is_null($paymentMethod->getCustomFields())
            && array_key_exists('mollie_payment_method_name', $paymentMethod->getCustomFields())) {
            $molliePaymentMethod = $paymentMethod->getCustomFields()['mollie_payment_method_name'];
        }

        if (is_null($molliePaymentMethod) ||
            ! in_array($molliePaymentMethod, ['klarnapaylater', 'klarnasliceit'])) {
            return;
        }

        $customFields = $order->getCustomFields();

        $mollieOrderId = null;

        if (! is_null($customFields) &&
            array_key_exists(CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS, $customFields) &&
            array_key_exists('order_id', $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS])) {
            $mollieOrderId = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['order_id'];
        }

        if (is_null($mollieOrderId)) {
            return;
        }
        $apiClient = $this->apiFactory->getClient($order->getSalesChannelId());

        $mollieOrder = $apiClient->orders->get($mollieOrderId);

        if (in_array($mollieOrder->status, ['created', 'authorized', 'shipping'])) {
            $apiClient->orders->cancel($mollieOrderId);
        }
    }
}
