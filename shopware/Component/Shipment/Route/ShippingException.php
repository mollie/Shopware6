<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class ShippingException extends HttpException
{
    public const ORDER_NOT_FOUND = 'ORDER_NOT_FOUND';
    public const LINE_ITEM_NOT_FOUND = 'LINE_ITEM_NOT_FOUND';
    public const LINE_ITEM_ALREADY_SHIPPED = 'LINE_ITEM_ALREADY_SHIPPED';
    public const SHIPPING_QUANTITY_TOO_HIGH = 'SHIPPING_QUANTITY_TOO_HIGH';
    public const SHIPPING_COSTS_ALREADY_CALCULATED = 'SHIPPING_COSTS_ALREADY_CALCULATED';
    public const NO_LINE_ITEMS = 'NO_LINE_ITEMS';

    public static function orderNotFound(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ORDER_NOT_FOUND,
            'Order {{orderId}} not found',[
                'orderId' => $orderId,
            ]
        );
    }

    public static function lineItemNotFound(string $oldCaptureId, string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LINE_ITEM_NOT_FOUND,
            'LineItem with oldCaptureId {{oldCaptureId}} in order {{orderId}} not found',[
                'oldCaptureId' => $oldCaptureId,
                'orderId' => $orderId,
            ]
        );
    }

    public static function lineItemAlreadyShipped(string $lineItemId, string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LINE_ITEM_ALREADY_SHIPPED,
            'LineItem {{lineItemId}} already shipped in order {{orderId}}',[
                'lineItemId' => $lineItemId,
                'orderId' => $orderId,
            ]
        );
    }

    public static function shippingQuantityTooHigh(string $lineItemId, string $orderId, int $newQuantity, int $quantity): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SHIPPING_QUANTITY_TOO_HIGH,
            'New Quantity {{newQuantity}} from lineItem {{lineItemId}} is higher than Shipping Quantity {{quantity}} {{orderId}} not found',[
                'lineItemId' => $lineItemId,
                'orderId' => $orderId,
                'newQuantity' => $newQuantity,
                'quantity' => $quantity
            ]
        );
    }

    public static function shippingCostsAlreadyCalculated(string $deliveryId, string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SHIPPING_COSTS_ALREADY_CALCULATED,
            'Delivery {{deliveryId}} already calculated all Shipping Costs for Order {{orderId}}',[
                'lineItemId' => $deliveryId,
                'orderId' => $orderId,
            ]
        );
    }

    public static function noLineItems(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_LINE_ITEMS,
            'No Line Items for Order {{orderId}}',[
                'orderId' => $orderId,
            ]
        );
    }
}
