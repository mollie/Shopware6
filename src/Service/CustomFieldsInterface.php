<?php

namespace Kiener\MolliePayments\Service;

interface CustomFieldsInterface
{
    /**
     *
     */
    public const MOLLIE_KEY = 'mollie_payments';

    /**
     *
     */
    public const ORDER_KEY = 'order_id';

    /**
     *
     */
    public const ORDER_LINE_KEY = 'order_line_id';

    /**
     *
     */
    public const DELIVERY_SHIPPED = 'is_shipped';
}
