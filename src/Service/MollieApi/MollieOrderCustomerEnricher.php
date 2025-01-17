<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException as CustomerCouldNotBeFoundExceptionAlias;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MollieOrderCustomerEnricher
{
    /**
     * @var CustomerService
     */
    private $customerService;


    /**
     * @param CustomerService $customerService
     */
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * @param array<mixed> $orderData
     * @param CustomerEntity $customer
     * @param MollieSettingStruct $settings
     * @param SalesChannelContext $salesChannelContext
     * @throws CustomerCouldNotBeFoundExceptionAlias
     * @return array<mixed>
     */
    public function enrich(array $orderData, CustomerEntity $customer, MollieSettingStruct $settings, SalesChannelContext $salesChannelContext): array
    {
        $customerStruct = $this->customerService->getCustomerStruct($customer->getId(), $salesChannelContext->getContext());
        $customerId = $customerStruct->getCustomerId((string)$settings->getProfileId(), $settings->isTestMode());

        if (empty($customerId)) {
            return $orderData;
        }

        $orderData['payment']['customerId'] = $customerId;

        return $orderData;
    }
}
