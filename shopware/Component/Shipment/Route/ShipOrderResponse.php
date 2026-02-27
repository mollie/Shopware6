<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class ShipOrderResponse extends StoreApiResponse
{
    /**
     * @param array<string, mixed>[] $updatedLineItems
     */
    public function __construct(string $captureId, string $orderId, array $updatedLineItems = [])
    {
        parent::__construct(new ArrayStruct(
            [
                'status' => 'success',
                'orderId' => $orderId,
                'captureId' => $captureId,
                'updatedLineItems' => $updatedLineItems,
            ],
            'mollie_ship_order_response'
        ));
    }
}
