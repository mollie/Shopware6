<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Service;


interface CustomFieldsInterface
{
    public const MOLLIE_KEY = 'mollie_payments';

    public const ORDER_KEY = 'order_id';

    public const DELIVERY_SHIPPED = 'is_shipped';
}
