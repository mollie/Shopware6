<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

/**
 * The address Mollie reports when the shopper picks one inside the express component.
 * It is deliberately coarse: wallets only hand out what is needed to price the delivery.
 */
final class ShippingCallbackAddress
{
    public function __construct(
        private string $country,
        private string $postalCode = '',
        private string $city = '',
        private string $region = ''
    ) {
    }

    /**
     * @param array<mixed> $body shippingAddress part of the callback payload
     */
    public static function fromArray(array $body): self
    {
        return new self(
            (string) ($body['country'] ?? ''),
            (string) ($body['postalCode'] ?? ''),
            (string) ($body['city'] ?? ''),
            (string) ($body['region'] ?? ''),
        );
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getRegion(): string
    {
        return $this->region;
    }
}
