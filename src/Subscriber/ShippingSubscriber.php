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
    )
    {
        $this->shipmentFacade = $shipmentFacade;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            MollieOrderShipmentTrackingEvent::class => 'onShipOrderWithTracking',
        ];
    }

    public function onShipOrderWithTracking(MollieOrderShipmentTrackingEvent $event)
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
            // it obviously cannot get shipped with Mollie. But if we don't catch it, the rest of the proces breaks.
        } catch (\Exception $e) {
            // We log the error, but don't rethrow so the rest of the proces can continue.
            $this->logger->error($e->getMessage());
        }
    }
}