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

    public const PAYMENT_KEY = 'payment_id';

    public const THIRD_PARTY_PAYMENT_KEY = 'third_party_payment_id';

    public const PAYPAL_EXPRESS_SESSION_ID_KEY = 'mollie_ppe_session_id';

    public const PAYPAL_EXPRESS_AUTHENTICATE_ID = 'mollie_ppe_auth_id';

    public const ACCEPTED_DATA_PROTECTION = 'acceptedDataProtection';
}
