<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class Session extends Struct implements \JsonSerializable
{
    use JsonSerializableTrait;

    private string $authenticationId;
    private string $redirectUrl;
    private ?Address $billingAddress = null;
    private ?Address $shippingAddress = null;

    public function __construct(private string $id)
    {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $session = new self($body['id']);
        $authenticateId = $body['authenticationId'] ?? '';
        $redirectUrl = $body['_links']['redirect']['href'] ?? '';
        $session->setAuthenticationId($authenticateId);
        $session->setRedirectUrl($redirectUrl);

        $shippingAddress = $body['shippingAddress'] ?? null;
        $billingAddress = $body['billingAddress'] ?? null;

        if ($shippingAddress) {
            if (! isset($shippingAddress['givenName'])) {
                $shippingAddress['givenName'] = $billingAddress['givenName'] ?? null;
                $shippingAddress['familyName'] = $billingAddress['familyName'] ?? null;
            }
            if (! isset($shippingAddress['email'])) {
                $shippingAddress['email'] = $billingAddress['email'] ?? null;
            }

            if (! isset($shippingAddress['phone'])) {
                $shippingAddress['phone'] = $billingAddress['phone'] ?? null;
            }
            $session->shippingAddress = Address::fromResponseBody($shippingAddress);
        }

        if ($billingAddress) {
            if ($billingAddress['streetAndNumber'] === null) {
                $billingAddress['streetAndNumber'] = $shippingAddress['streetAndNumber'] ?? '';
            }
            if ($billingAddress['streetAdditional'] === null) {
                $billingAddress['streetAdditional'] = $shippingAddress['streetAdditional'] ?? '';
            }
            if ($billingAddress['city'] === null) {
                $billingAddress['city'] = $shippingAddress['city'] ?? '';
            }
            if ($billingAddress['postalCode'] === null) {
                $billingAddress['postalCode'] = $shippingAddress['postalCode'] ?? '';
            }
            $session->billingAddress = Address::fromResponseBody($billingAddress);
        }

        return $session;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAuthenticationId(): string
    {
        return $this->authenticationId;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function setAuthenticationId(string $authenticationId): void
    {
        $this->authenticationId = $authenticationId;
    }

    public function setRedirectUrl(string $redirectUrl): void
    {
        $this->redirectUrl = $redirectUrl;
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }
}
