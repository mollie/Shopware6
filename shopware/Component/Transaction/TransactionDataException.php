<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class TransactionDataException extends HttpException
{
    public const TRANSACTION_NOT_FOUND = 'TRANSACTION_NOT_FOUND';
    public const TRANSACTION_ORDER_NOT_FOUND = 'TRANSACTION_ORDER_NOT_FOUND';
    public const ORDER_WITHOUT_DELIVERIES = 'ORDER_WITHOUT_DELIVERIES';
    public const ORDER_WITHOUT_LANGUAGE = 'ORDER_WITHOUT_LANGUAGE';
    public const ORDER_WITHOUT_CURRENCY = 'ORDER_WITHOUT_CURRENCY';
    public const ORDER_DELIVERY_WITHOUT_ADDRESS = 'ORDER_DELIVERY_WITHOUT_ADDRESS';
    public const ORDER_WITHOUT_BILLING_ADDRESS = 'ORDER_WITHOUT_BILLING_ADDRESS';

    public static function transactionNotFound(string $transactionId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TRANSACTION_NOT_FOUND,
            'Transaction {{transactionId}} not found in Shopware',
            ['transactionId' => $transactionId],
            $exception
        );
    }

    public static function oderNotExists(string $transactionId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TRANSACTION_ORDER_NOT_FOUND,
            'Transaction {{transactionId}} does not have an Order in Shopware',
            ['transactionId' => $transactionId],
            $exception
        );
    }

    public static function orderWithoutDeliveries(string $orderId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ORDER_WITHOUT_DELIVERIES,
            'Order {{orderId}} does not have delivery addresses in Shopware',
            ['orderId' => $orderId],
            $exception
        );
    }

    public static function orderWithoutLanguage(string $orderId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ORDER_WITHOUT_LANGUAGE,
            'Order {{orderId}} does not have language in Shopware',
            ['orderId' => $orderId],
            $exception
        );
    }

    public static function orderWithoutCurrency(string $orderId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ORDER_WITHOUT_CURRENCY,
            'Order {{orderId}} does not have a currency in Shopware',
            ['orderId' => $orderId],
            $exception
        );
    }

    public static function orderDeliveryWithoutShippingAddress(string $orderId, string $deliveryAddressId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ORDER_DELIVERY_WITHOUT_ADDRESS,
            'Delivery address {{deliveryAddressId}} from order {{orderId}} does shipping address in Shopware',
            ['orderId' => $orderId, 'deliveryAddressId' => $deliveryAddressId],
            $exception
        );
    }

    public static function orderWithoutCustomer(string $orderId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ORDER_DELIVERY_WITHOUT_ADDRESS,
            'Order {{orderId}} does not have a customer in Shopware',
            ['orderId' => $orderId],
            $exception
        );
    }

    public static function orderWithoutSalesChannel(string $orderId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ORDER_DELIVERY_WITHOUT_ADDRESS,
            'Order {{orderId}} does not have a sales channel in Shopware',
            ['orderId' => $orderId],
            $exception
        );
    }

    public static function orderWithoutBillingAddress(string $orderId, ?\Throwable $exception = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ORDER_WITHOUT_BILLING_ADDRESS,
            'Order {{orderId}} does not have a billing address in Shopware',
            ['orderId' => $orderId],
            $exception
        );
    }
}
