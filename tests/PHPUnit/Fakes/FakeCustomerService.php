<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes;

use Kiener\MolliePayments\Service\CustomerServiceInterface;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Kiener\MolliePayments\Struct\CustomerStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeCustomerService implements CustomerServiceInterface
{
    private EntityWrittenContainerEvent $setCardTokenResponse;
    private EntityWrittenContainerEvent $setMandateIdResponse;
    private ?CustomerEntity $customerEntity = null;
    private bool $throwException;

    public function __construct(bool $throwException = false)
    {
        $this->setCardTokenResponse = new EntityWrittenContainerEvent(new Context(new SystemSource()), new NestedEventCollection(), []);
        $this->setMandateIdResponse = new EntityWrittenContainerEvent(new Context(new SystemSource()), new NestedEventCollection(), []);
        $this->throwException = $throwException;
    }

    public function loginCustomer(CustomerEntity $customer, SalesChannelContext $context): ?string
    {
        return null;
    }

    public function isCustomerLoggedIn(SalesChannelContext $context): bool
    {
        return false;
    }

    public function setCardToken(CustomerEntity $customer, string $cardToken, SalesChannelContext $context, bool $shouldSaveCardDetail = false): EntityWrittenContainerEvent
    {
        return $this->setCardTokenResponse;
    }

    public function setMandateId(CustomerEntity $customer, string $cardToken, Context $context): EntityWrittenContainerEvent
    {
        return $this->setMandateIdResponse;
    }

    public function saveCustomerCustomFields(string $customerID, array $customFields, Context $context): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent();
    }

    public function setIDealIssuer(CustomerEntity $customer, string $issuerId, Context $context): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent();
    }

    public function getMollieCustomerId(string $customerId, string $salesChannelId, Context $context): string
    {
        return '';
    }

    public function setMollieCustomerId(string $customerId, string $mollieCustomerId, string $profileId, bool $testMode, Context $context): void
    {
        // TODO: Implement setMollieCustomerId() method.
    }

    public function getCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        return $this->customerEntity;
    }

    public function getCustomerStruct(string $customerId, Context $context): CustomerStruct
    {
        return new CustomerStruct();
    }

    public function getAddressArray($address, CustomerEntity $customer): array
    {
        return [];
    }

    public function createGuestAccount(AddressStruct $shippingAddress, string $paymentMethodId, SalesChannelContext $context, ?int $acceptedDataProtection, ?AddressStruct $billingAddress = null): ?CustomerEntity
    {
        return null;
    }

    public function getCountryId(string $countryCode, Context $context): ?string
    {
        return null;
    }

    public function getSalutationId(Context $context): ?string
    {
        return null;
    }

    public function createMollieCustomer(string $customerId, string $salesChannelId, Context $context): void
    {
        // TODO: Implement createMollieCustomer() method.
    }

    public function withCardTokenErrors(array $errors): self
    {
        $customerService = clone $this;
        $customerService->setCardTokenResponse = new EntityWrittenContainerEvent(new Context(new SystemSource()), new NestedEventCollection(), $errors);

        return $customerService;
    }

    public function withSaveMandateIdErrors(array $errors): self
    {
        $customerService = clone $this;
        $customerService->setMandateIdResponse = new EntityWrittenContainerEvent(new Context(new SystemSource()), new NestedEventCollection(), $errors);

        return $customerService;
    }

    public function withFakeCustomer(): self
    {
        $customerService = clone $this;
        $customerService->customerEntity = new CustomerEntity();

        return $customerService;
    }
}
