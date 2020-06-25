<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\DeliveryService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDeliverySubscriber implements EventSubscriberInterface
{
    private const PARAM_ID = 'id';
    private const PARAM_ORDER_ID = 'order_id';
    private const PARAM_CUSTOM_FIELDS = 'customFields';
    private const PARAM_MOLLIE_PAYMENTS = 'mollie_payments';
    private const PARAM_IS_SHIPPED = 'is_shipped';

    /** @var MollieApiClient */
    private $apiClient;

    /** @var DeliveryService */
    private $deliveryService;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'onOrderDeliveryWritten'
        ];
    }

    /**
     * Creates a new instance of PaymentMethodSubscriber.
     *
     * @param MollieApiClient $apiClient
     * @param DeliveryService $deliveryService
     */
    public function __construct(
        MollieApiClient $apiClient,
        DeliveryService $deliveryService
    )
    {
        $this->apiClient = $apiClient;
        $this->deliveryService = $deliveryService;
    }

    /**
     * Refunds the transaction at Mollie if the payment state is refunded.
     *
     * @param EntityWrittenEvent $args
     * @throws ApiException
     */
    public function onOrderDeliveryWritten(EntityWrittenEvent $args): void
    {
        foreach ($args->getPayloads() as $payload) {
            $deliveryId = $payload['id'];
            $deliveryVersionId = $payload['versionId'];
            $order = null;
            $customFields = null;
            $mollieOrder = null;

            if (!isset($payload['stateId'])) {
                continue;
            }

            try {
                /** @var OrderDeliveryEntity $delivery */
                $delivery = $this->deliveryService->getDeliveryById(
                    $deliveryId,
                    $deliveryVersionId,
                    $args->getContext()
                );
            } catch (InconsistentCriteriaIdsException $e) {
                // @todo Handle exception
            }

            // Get the order from the transaction
            if (
                $delivery !== null
            ) {
                $order = $delivery->getOrder();
            }

            // Get the custom fields from the order
            if (
                $order !== null
                && $order->getCustomFields() !== null
            ) {
                $customFields = $order->getCustomFields();
            }

            // Get the order at Mollie
            if (
                $customFields !== null
                && isset($customFields[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_ORDER_ID])
            ) {
                try {
                    if ($this->apiClient->usesOAuth()) {
                        $parameters = [
                            'testmode' => false,
                        ];
                    }

                    $mollieOrder = $this->apiClient->orders->get(
                        $customFields[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_ORDER_ID],
                        $parameters ?? []
                    );
                } catch (\Exception $e) {
                    //
                }

                if ($mollieOrder === null) {
                    if ($this->apiClient->usesOAuth()) {
                        $parameters = [
                            'testmode' => true,
                        ];
                    }

                    $mollieOrder = $this->apiClient->orders->get(
                        $customFields[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_ORDER_ID],
                        $parameters ?? []
                    );
                }
            }

            // Ship the order
            if (
                $mollieOrder !== null
                && $delivery->getStateMachineState() !== null
                && $delivery->getStateMachineState()->getTechnicalName() === OrderDeliveryStates::STATE_SHIPPED
                && (
                    !isset($delivery->getCustomFields()[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_IS_SHIPPED])
                    || $delivery->getCustomFields()[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_IS_SHIPPED] === false
                )
            ) {
                // Ship the order at Mollie
                $mollieOrder->shipAll();

                // Add is shipped flag to custom fields
                $this->deliveryService->updateDelivery([
                    self::PARAM_ID => $delivery->getId(),
                    self::PARAM_CUSTOM_FIELDS => $this->deliveryService->addShippedToCustomFields($order->getCustomFields(), true),
                ], $args->getContext());
            }
        }
    }
}