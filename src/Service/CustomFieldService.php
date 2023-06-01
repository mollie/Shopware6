<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Exception;
use Kiener\MolliePayments\Repository\CustomFieldSet\CustomFieldSetRepositoryInterface;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldService
{
    public const CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS = 'mollie_payments';
}
