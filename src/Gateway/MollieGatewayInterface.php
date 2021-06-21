<?php

namespace Kiener\MolliePayments\Gateway;


use Mollie\Api\Resources\Order;


interface MollieGatewayInterface
{

    /**
     * @param string $salesChannelID
     */
    public function switchClient(string $salesChannelID): void;

    /**
     * @return string
     */
    public function getOrganizationId(): string;

    /**
     * @return string
     */
    public function getProfileId(): string;

    /**
     * @param string $orderId
     * @return Order
     */
    public function getOrder(string $orderId): Order;

}
