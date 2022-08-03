<?php

namespace Kiener\MolliePayments\Event;

use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class PaymentPageRedirectEvent extends Event
{
    public const EVENT_NAME = 'mollie.payment.page.redirect';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var null|OrderEntity
     */
    private $shopwareOrder;

    /**
     * @var null|Order
     */
    private $mollieOrder;

    /**
     * @var null|string
     */
    private $salesChannelId;

    /**
     * @var null|string
     */
    private $redirectUrl;

    public function __construct(
        Context $context,
        ?OrderEntity $shopwareOrder = null,
        ?Order $mollieOrder = null,
        ?string $salesChannelId = null,
        ?string $redirectUrl = null
    ) {
        $this->context = $context;
        $this->shopwareOrder = $shopwareOrder;
        $this->mollieOrder = $mollieOrder;
        $this->salesChannelId = $salesChannelId;
        $this->redirectUrl = $redirectUrl;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getShopwareOrder(): ?OrderEntity
    {
        return $this->shopwareOrder;
    }

    public function getMollieOrder(): ?Order
    {
        return $this->mollieOrder;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }
}
