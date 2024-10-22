<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Kiener\MolliePayments\Struct\CustomerStruct;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CustomerServiceInterface
{
    public function loginCustomer(CustomerEntity $customer, SalesChannelContext $context): ?string;

    public function isCustomerLoggedIn(SalesChannelContext $context): bool;

    public function setCardToken(CustomerEntity $customer, string $cardToken, SalesChannelContext $context, bool $shouldSaveCardDetail = false): EntityWrittenContainerEvent;

    public function setMandateId(CustomerEntity $customer, string $cardToken, Context $context): EntityWrittenContainerEvent;

    /**
     * @param string $customerID
     * @param array<mixed> $customFields
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function saveCustomerCustomFields(string $customerID, array $customFields, Context $context): EntityWrittenContainerEvent;
    public function getMollieCustomerId(string $customerId, string $salesChannelId, Context $context): string;

    public function setMollieCustomerId(string $customerId, string $mollieCustomerId, string $profileId, bool $testMode, Context $context): void;

    public function getCustomer(string $customerId, Context $context): ?CustomerEntity;

    public function getCustomerStruct(string $customerId, Context $context): CustomerStruct;

    /**
     * @param null|CustomerAddressEntity|OrderAddressEntity $address
     * @param CustomerEntity $customer
     * @return array<string, mixed>
     */
    public function getAddressArray($address, CustomerEntity $customer): array;

    public function createGuestAccount(AddressStruct $shippingAddress, string $paymentMethodId, SalesChannelContext $context, ?int $acceptedDataProtection, ?AddressStruct $billingAddress = null): ?CustomerEntity;

    public function getCountryId(string $countryCode, Context $context): ?string;

    public function getSalutationId(Context $context): ?string;

    public function createMollieCustomer(string $customerId, string $salesChannelId, Context $context): void;
}
