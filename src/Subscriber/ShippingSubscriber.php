<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Event\MollieOrderShipmentTrackingEvent;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Facade\MollieShipment;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShippingSubscriber implements EventSubscriberInterface
{
    /**
     * @var MollieShipment
     */
    private $shipmentFacade;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        MollieShipment  $shipmentFacade,
        LoggerInterface $logger
    ) {
        $this->shipmentFacade = $shipmentFacade;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            MollieOrderShipmentTrackingEvent::class => 'onShipOrderWithTracking',
        ];
    }

    /**
     * @param MollieOrderShipmentTrackingEvent $event
     */
    public function onShipOrderWithTracking(MollieOrderShipmentTrackingEvent $event): void
    {
        try {
            $this->shipmentFacade->shipOrderByOrderId(
                $event->getOrderId(),
                $event->getTrackingCarrier(),
                $event->getTrackingCode(),
                $event->getTrackingUrl(),
                $event->getContext()
            );
        } catch (CouldNotExtractMollieOrderIdException $e) {
            // We need to catch CouldNotExtractMollieOrderIdException, because if it's not a Mollie Order
            // it obviously cannot get shipped with Mollie. We also don't have to log this, except for debugging.
            // But if we don't catch it, the rest of the process might break.
            $this->logger->debug($e->getMessage(), [
                'orderId' => $event->getOrderId(),
                'trackingCarrier' => $event->getTrackingCarrier(),
                'trackingCode' => $event->getTrackingCode(),
                'trackingUrl' => $event->getTrackingUrl(),
            ]);
        } catch (\Exception $e) {
            // We log the error, but don't rethrow so the rest of the proces can continue.
            $this->logger->error(
                sprintf(
                    "Error when shipping order from Mollie Event: \"%s\" in \"%s\" on line %s",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ),
                [
                    'orderId' => $event->getOrderId(),
                    'trackingCarrier' => $event->getTrackingCarrier(),
                    'trackingCode' => $event->getTrackingCode(),
                    'trackingUrl' => $event->getTrackingUrl(),
                ]
            );
        }
    }
}
