<?php


namespace Kiener\MolliePayments\Service;


/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
interface CustomFieldsInterface
{
    public const MOLLIE_KEY = 'mollie_payments';

    public const ORDER_KEY = 'order_id';

    public const DELIVERY_SHIPPED = 'is_shipped';
}
