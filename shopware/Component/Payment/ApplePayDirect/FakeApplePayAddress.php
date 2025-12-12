<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * In Shopware, shipping costs are calculated based on rules for countries.
 * The country is ignored if a customer is already logged in, since his selected shipping address is used
 * in this case, we create a fake shipping address with the country from apple pay wallet data
 */
final class FakeApplePayAddress
{
    private const ID_SUFFIX = 'applePayAddressId';

    public function __construct(private CustomerEntity $customer,private string $countryId)
    {
    }

    public static function getId(CustomerEntity $customer): string
    {
        return Uuid::fromBytesToHex(md5($customer->getId() . '-' . self::ID_SUFFIX, true));
    }

    /**
     * @return array<mixed>
     */
    public function toUpsertArray(): array
    {
        return [
            'id' => $this->getId($this->customer),
            'salutationId' => $this->customer->getSalutationId(),
            'countryId' => $this->countryId,
            'customerId' => $this->customer->getId(),
            'firstName' => $this->customer->getFirstName(),
            'lastName' => $this->customer->getLastName(),
            'city' => 'not provided', // city is not necessary for rule builder
            'street' => 'not provided', // apple pay event "onshippingcontactselected"  does not provide a street https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778009-onshippingcontactselected
        ];
    }
}
