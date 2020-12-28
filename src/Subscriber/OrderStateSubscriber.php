<?php


namespace Kiener\MolliePayments\Subscriber;


use Kiener\MolliePayments\Service\CustomFieldService;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        // Build order state change to cancelled event name
        $orderStateCancelled = implode('.', [
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            OrderStates::STATE_MACHINE,
            OrderStates::STATE_CANCELLED
        ]);

        return [
            $orderStateCancelled => ['onEnterOrderStateCancelled']
        ];
    }

    /** @var MollieApiClient $apiClient */
    private $apiClient;

    public function __construct(
        MollieApiClient $apiClient
    )
    {
        $this->apiClient = $apiClient;
    }

    public function onEnterOrderStateCancelled(OrderStateMachineStateChangeEvent $event)
    {
        $molliePaymentMethod = null;

        // use filterByState(OrderTransactionStates::STATE_OPEN)?
        $paymentMethod = $event->getOrder()->getTransactions()->last()->getPaymentMethod();
        if (!is_null($paymentMethod->getCustomFields())
            && array_key_exists('mollie_payment_method_name', $paymentMethod->getCustomFields())) {
            $molliePaymentMethod = $paymentMethod->getCustomFields()['mollie_payment_method_name'];
        }

        if(is_null($molliePaymentMethod) || !in_array($molliePaymentMethod, ['klarnapaylater', 'klarnasliceit'])) {
            return;
        }

        $customFields = $event->getOrder()->getCustomFields();

        $mollieOrderId = null;

        if (!is_null($customFields) &&
            array_key_exists(CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS, $customFields) &&
            array_key_exists('order_id', $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS])) {
            $mollieOrderId = $event->getOrder()->getCustomFields()[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['order_id'];
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
