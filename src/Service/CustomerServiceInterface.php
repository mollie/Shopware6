<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Struct\CustomerStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CustomerServiceInterface
{
    public function customerLogin(CustomerEntity $customer, SalesChannelContext $context): ?string;
    public function isCustomerLoggedIn(SalesChannelContext $context): bool;
    public function setCardToken(CustomerEntity $customer, string $cardToken, Context $context): EntityWrittenContainerEvent;
    public function saveCustomerCustomFields(string $customerID, array $customFields, Context $context): EntityWrittenContainerEvent;
    public function setIDealIssuer(CustomerEntity $customer, string $issuerId, Context $context): EntityWrittenContainerEvent;
    public function getMollieCustomerId(string $customerId, string $salesChannelId, Context $context): string;
    public function setMollieCustomerId(string $customerId, string $mollieCustomerId, string $profileId, bool $testMode, Context $context): void;
    public function getCustomer(string $customerId, Context $context): ?CustomerEntity;
    public function getCustomerStruct(string $customerId, Context $context): CustomerStruct;
    public function getAddressArray($address, CustomerEntity $customer): array;
    public function createApplePayDirectCustomer(string $firstname, string $lastname, string $email, string $phone, string $street, string $zipCode, string $city, string $countryISO2, string $paymentMethodId, SalesChannelContext $context): ?CustomerEntity;
    public function getCountryId(string $countryCode, Context $context): ?string;
    public function getSalutationId(Context $context): ?string;
    public function createMollieCustomer(string $customerId, string $salesChannelId, Context $context): void;
}
