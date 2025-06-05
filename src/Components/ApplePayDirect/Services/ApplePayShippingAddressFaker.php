<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Services;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * We have to create a Fake Address in Case for Apple Pay Direct in order to load te correct shipping costs.
 * The shipping costs are loaded based on rules, if a customer is already logged in and using apple pay direct, then the country from his current address is used for the rules.
 * we have to add a temporary fake address to this customer in order to load correct shipping costs. afterwards we clear them
 */
class ApplePayShippingAddressFaker
{
    private const ID_SUFFIX = 'applePayAddressId';
    /** @var EntityRepository<EntityCollection<CustomerEntity>> */
    private $customerRepository;
    /** @var EntityRepository<EntityCollection<CustomerAddressEntity>> */
    private $customerAddressRepository;

    /**
     * @param EntityRepository<EntityCollection<CustomerEntity>> $customerRepository
     * @param EntityRepository<EntityCollection<CustomerAddressEntity>> $customerAddressRepository
     */
    public function __construct(
        $customerRepository,
        $customerAddressRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerAddressRepository = $customerAddressRepository;
    }

    public function createFakeShippingAddress(string $countryId, CustomerEntity $customerEntity, Context $context): string
    {
        $applePayAddressId = $this->generateAddressId($customerEntity);

        $this->customerRepository->update([
            [
                'id' => $customerEntity->getId(),
                'addresses' => [
                    [
                        'id' => $applePayAddressId,
                        'salutationId' => $customerEntity->getSalutationId(),
                        'countryId' => $countryId,
                        'firstName' => $customerEntity->getFirstName(),
                        'lastName' => $customerEntity->getLastName(),
                        'city' => 'not provided', // city is not necessary for rule builder
                        'street' => 'not provided', // apple pay event "onshippingcontactselected"  does not provide a street https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778009-onshippingcontactselected
                    ],
                ],
            ],
        ], $context);

        return $applePayAddressId;
    }

    public function deleteFakeShippingAddress(CustomerEntity $customerEntity, Context $context): void
    {
        $applePayAddressId = $this->generateAddressId($customerEntity);
        $this->customerAddressRepository->delete([
            [
                'id' => $applePayAddressId,
            ],
        ], $context);
    }

    private function generateAddressId(CustomerEntity $customerEntity): string
    {
        /* We cant use here Uuid::fromString because it does not exists in SW6.4 */
        return Uuid::fromBytesToHex(md5($customerEntity->getId() . '-' . self::ID_SUFFIX, true));
    }
}
