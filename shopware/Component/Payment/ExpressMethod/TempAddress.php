<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Temporary customer address used to price an express checkout for an address the shopper
 * picked inside a wallet.
 *
 * Shopware calculates shipping costs from rules that match the shipping address, but for a
 * logged in customer it always uses the address stored on the account and ignores anything
 * passed in. To price the wallet address anyway, it is written as a temporary address, used
 * for the calculation, and deleted again afterwards.
 */
final class TempAddress
{
    private const ID_SUFFIX = 'expressAddressId';

    private const NOT_PROVIDED = 'not provided';

    public function __construct(
        private CustomerEntity $customer,
        private string $countryId,
        private ?string $city = null,
        private ?string $zipcode = null
    ) {
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
        $address = [
            'id' => $this->getId($this->customer),
            'salutationId' => $this->customer->getSalutationId(),
            'countryId' => $this->countryId,
            'customerId' => $this->customer->getId(),
            'firstName' => $this->customer->getFirstName(),
            'lastName' => $this->customer->getLastName(),
            // the wallet callbacks do not provide a street, and neither field is needed by the rule
            // builder. Both are required by Shopware though, so they never may end up blank.
            'city' => $this->city !== null && $this->city !== '' ? $this->city : self::NOT_PROVIDED,
            'street' => self::NOT_PROVIDED,
        ];

        // cart rules can filter on the postal code, so it is sent whenever the wallet knows it
        if ($this->zipcode !== null && $this->zipcode !== '') {
            $address['zipcode'] = $this->zipcode;
        }

        return $address;
    }
}
