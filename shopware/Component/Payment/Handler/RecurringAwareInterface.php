<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

interface RecurringAwareInterface
{
    public const FIELD_SAVE_PAYMENT_DETAILS = 'savePaymentDetails';

    public const FIELD_MANDATE_ID = 'mandateId';
}
