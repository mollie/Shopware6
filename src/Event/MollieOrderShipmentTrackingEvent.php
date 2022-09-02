<?php

namespace Kiener\MolliePayments\Event;

use Shopware\Core\Framework\Context;

class MollieOrderShipmentTrackingEvent
{
    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $trackingCarrier;

    /**
     * @var string
     */
    protected $trackingCode;

    /**
     * @var string
     */
    protected $trackingUrl;


    public function __construct(
        string  $orderId,
        Context $context,
        string  $trackingCarrier,
        string  $trackingCode,
        string  $trackingUrl
    ) {
        $this->orderId = $orderId;
        $this->context = $context;
        $this->trackingCarrier = $trackingCarrier;
        $this->trackingCode = $trackingCode;
        $this->trackingUrl = $trackingUrl;
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @param string $orderId
     */
    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @param Context $context
     */
    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getTrackingCarrier(): string
    {
        return $this->trackingCarrier;
    }

    /**
     * @param string $trackingCarrier
     */
    public function setTrackingCarrier(string $trackingCarrier): void
    {
        $this->trackingCarrier = $trackingCarrier;
    }

    /**
     * @return string
     */
    public function getTrackingCode(): string
    {
        return $this->trackingCode;
    }

    /**
     * @param string $trackingCode
     */
    public function setTrackingCode(string $trackingCode): void
    {
        $this->trackingCode = $trackingCode;
    }

    /**
     * @return string
     */
    public function getTrackingUrl(): string
    {
        return $this->trackingUrl;
    }

    /**
     * @param string $trackingUrl
     */
    public function setTrackingUrl(string $trackingUrl): void
    {
        $this->trackingUrl = $trackingUrl;
    }
}
