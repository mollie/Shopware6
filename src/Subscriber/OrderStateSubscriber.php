<?php


namespace Kiener\MolliePayments\Subscriber;


use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
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

    /** @var MollieApiClient $apiClient */
    private $apiClient;

    /** @var OrderService */
    private $orderService;

    /** @var PaymentMethodService */
    private $paymentMethodService;

    public function __construct(
        MollieApiClient $apiClient,
        OrderService $orderService,
        PaymentMethodService $paymentMethodService
    )
    {
        $this->apiClient = $apiClient;
        $this->orderService = $orderService;
        $this->paymentMethodService = $paymentMethodService;
    }

    public function onKlarnaOrderCancelledAsAdmin(StateMachineStateChangeEvent $event)
    {
        if(!($event->getContext()->getSource() instanceof AdminApiSource)) {
            return;
        }

        // Build order state change to cancelled event name
        $orderStateCancelled = implode('.', [
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            OrderStates::STATE_MACHINE,
            OrderStates::STATE_CANCELLED
        ]);

        if($event->getStateEventName() !== $orderStateCancelled) {
            return;
        }

        $order = $this->orderService->getOrder($event->getTransition()->getEntityId(), $event->getContext());

        // use filterByState(OrderTransactionStates::STATE_OPEN)?
        $lastTransaction = $order->getTransactions()->last();

        $paymentMethod = $lastTransaction->getPaymentMethod();

        if (is_null($paymentMethod) && !is_null($lastTransaction->getPaymentMethodId())) {
            $paymentMethod = $this->paymentMethodService->getPaymentMethodById($lastTransaction->getPaymentMethodId());
        }

        $molliePaymentMethod = null;

        if (!is_null($paymentMethod) && !is_null($paymentMethod->getCustomFields())
            && array_key_exists('mollie_payment_method_name', $paymentMethod->getCustomFields())) {
            $molliePaymentMethod = $paymentMethod->getCustomFields()['mollie_payment_method_name'];
        }

        if (is_null($molliePaymentMethod) ||
            !in_array($molliePaymentMethod, ['klarnapaylater', 'klarnasliceit'])) {
            return;
        }

        $customFields = $order->getCustomFields();

        $mollieOrderId = null;

        if (!is_null($customFields) &&
            array_key_exists(CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS, $customFields) &&
            array_key_exists('order_id', $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS])) {
            $mollieOrderId = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['order_id'];
        }

        if (is_null($mollieOrderId)) {
            return;
        }

        $mollieOrder = $this->apiClient->orders->get($mollieOrderId);

        if (in_array($mollieOrder->status, ['created', 'authorized', 'shipping'])) {
            $this->apiClient->orders->cancel($mollieOrderId);
        }
    }
}
