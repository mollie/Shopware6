<?php

namespace Mollie\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Tester\Exception\PendingException;
use Mollie\Integration\Data\CustomerTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;


final class CustomerContext extends ShopwareContext
{
    use CustomerTestBehaviour;

    /**
     * @Given iam logged in as user :arg1
     */
    public function iamLoggedInAsUser(string $email): void
    {

        $customerId = $this->loginOrCreateAccount($email, $this->getCurrentSalesChannelContext());
        $this->setOptions(SalesChannelContextService::CUSTOMER_ID,$customerId);
    }

    /**
     * @Given i select :arg1 as billing country
     */
    public function iSelectAsBillingCountry(string $billingCountry): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $addressIdSearchResult = $this->getUserAddressByIso($billingCountry, $salesChannelContext);
        $addressId = $addressIdSearchResult->firstId();
        $this->setOptions(SalesChannelContextService::BILLING_ADDRESS_ID,$addressId);
        $this->setOptions(SalesChannelContextService::SHIPPING_ADDRESS_ID,$addressId);
    }
}