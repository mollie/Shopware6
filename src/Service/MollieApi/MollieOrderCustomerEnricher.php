<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class MollieOrderCustomerEnricher
{
    public function enrich(array $orderData, CustomerEntity $customer, MollieSettingStruct $settings): array
    {
        if (!$settings->createCustomersAtMollie()) {
            return $orderData;
        }

        $customFields = $customer->getCustomFields() ?? [];

        $customerId = $customFields['customer_id'] ?? '';
        if (empty($customerId)) {
            return $orderData;
        }

        $orderData['payment']['customerId'] = $customerId;

        return $orderData;
    }
}
