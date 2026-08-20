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
    private string $clientAccessToken = '';
    private SessionStatus $status = SessionStatus::OPEN;
    private ?PaymentMethod $method = null;
    private ?string $cardToken = null;
    private ?string $wallet = null;
    private string $nextAction = '';
    private string $paymentId = '';
    private LineItemCollection $lines;
    private ShippingOptionCollection $shippingOptions;
    private ?Money $amount = null;
    private ?Address $billingAddress = null;
    private ?Address $shippingAddress = null;
    private bool $acceptedDataProtection = false;

    public function __construct(private string $id)
    {
        $this->lines = new LineItemCollection();
        $this->shippingOptions = new ShippingOptionCollection();
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
        $session->setClientAccessToken((string) ($body['clientAccessToken'] ?? ''));
        $session->setNextAction((string) ($body['nextAction'] ?? ''));

        $status = SessionStatus::tryFrom((string) ($body['status'] ?? ''));
        if ($status !== null) {
            $session->setStatus($status);
        }

        if (isset($body['amount']['value'], $body['amount']['currency'])) {
            $session->setAmount(Money::fromArray($body['amount']));
        }

        // the payment created for the session is not part of the body, only the return link
        // of the checkout carries its id: .../checkout/return/<id> belongs to payment tr_<id>
        $redirectId = trim((string) parse_url($redirectUrl, PHP_URL_PATH), '/');
        $redirectId = substr($redirectId, (int) strrpos($redirectId, '/') + 1);
        if ($redirectId !== '') {
            $session->setPaymentId('tr_' . $redirectId);
        }

        $session->setMethod(PaymentMethod::tryFrom((string) ($body['method'] ?? '')));
        $session->applyMethodDetails($body['methodDetails'] ?? []);

        foreach ($body['lines'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $session->lines->add(LineItem::createFromClientResponse($line));
        }

        $session->shippingOptions = ShippingOptionCollection::fromArray($body['shippingOptions'] ?? []);

        $shippingAddress = $body['shippingAddress'] ?? null;
        $billingAddress = $body['billingAddress'] ?? null;

        if ($shippingAddress) {
            if (! isset($shippingAddress['givenName']) || mb_strlen((string) $shippingAddress['givenName']) === 0) {
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
            // Mollie omits fields it has no value for instead of sending them as null, so the
            // keys cannot be read directly - an express session has no streetAdditional at all
            foreach (['streetAndNumber', 'streetAdditional', 'city', 'postalCode'] as $field) {
                if (($billingAddress[$field] ?? null) === null) {
                    $billingAddress[$field] = $shippingAddress[$field] ?? '';
                }
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

    public function getClientAccessToken(): string
    {
        return $this->clientAccessToken;
    }

    public function setClientAccessToken(string $clientAccessToken): void
    {
        $this->clientAccessToken = $clientAccessToken;
    }

    public function getAmount(): ?Money
    {
        return $this->amount;
    }

    public function setAmount(Money $amount): void
    {
        $this->amount = $amount;
    }

    public function getStatus(): SessionStatus
    {
        return $this->status;
    }

    public function setStatus(SessionStatus $status): void
    {
        $this->status = $status;
    }

    public function getMethod(): ?PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(?PaymentMethod $method): void
    {
        $this->method = $method;
    }

    /**
     * The token of the wallet the shopper paid with. It is the equivalent of the card token
     * of the credit card components and is used to create the payment.
     */
    public function getCardToken(): ?string
    {
        return $this->cardToken;
    }

    public function getWallet(): ?string
    {
        return $this->wallet;
    }

    public function getNextAction(): string
    {
        return $this->nextAction;
    }

    public function setNextAction(string $nextAction): void
    {
        $this->nextAction = $nextAction;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function setPaymentId(string $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function getLines(): LineItemCollection
    {
        return $this->lines;
    }

    /**
     * Mollie reports the shipping option the shopper picked as an additional shipping_fee
     * line, there is no dedicated field for it.
     */
    public function getSelectedShippingLine(): ?LineItem
    {
        foreach ($this->lines as $line) {
            if ($line->getType() === LineItemType::SHIPPING) {
                return $line;
            }
        }

        return null;
    }

    public function getShippingOptions(): ShippingOptionCollection
    {
        return $this->shippingOptions;
    }

    /**
     * The picked shipping option is not marked as such, Mollie only adds it to the lines as a
     * shipping_fee. Matching it back to the option - and with it to the Shopware shipping method
     * in its reference - therefore goes over description and amount. That is ambiguous as soon as
     * two shipping methods share both, and has to be replaced once Mollie exposes the selection.
     */
    public function getSelectedShippingOption(): ?ShippingOption
    {
        $shippingLine = $this->getSelectedShippingLine();
        if (! $shippingLine instanceof LineItem) {
            return null;
        }

        $amount = $shippingLine->getAmount();

        foreach ($this->shippingOptions as $shippingOption) {
            if ($shippingOption->getDescription() !== $shippingLine->getDescription()) {
                continue;
            }

            $optionAmount = $shippingOption->getAmount();
            if ($optionAmount->getCurrency() !== $amount->getCurrency()) {
                continue;
            }

            $decimals = $amount->getDecimals();
            if (round($optionAmount->getValue(), $decimals) !== round($amount->getValue(), $decimals)) {
                continue;
            }

            return $shippingOption;
        }

        return null;
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    /**
     * methodDetails carries the params below the key of the method that was used:
     * {"method": "creditcard", "params": {"creditcard": {"pspToken": "tkn_...", "wallet": "googlepay"}}}
     *
     * @param array<mixed> $methodDetails
     */
    private function applyMethodDetails(array $methodDetails): void
    {
        $method = (string) ($methodDetails['method'] ?? '');
        $params = $methodDetails['params'][$method] ?? null;
        if (! is_array($params)) {
            return;
        }

        $pspToken = $params['pspToken'] ?? null;
        if (is_string($pspToken) && $pspToken !== '') {
            $this->cardToken = $pspToken;
        }

        $wallet = $params['wallet'] ?? null;
        if (is_string($wallet) && $wallet !== '') {
            $this->wallet = $wallet;
        }
    }

    public function hasAcceptedDataProtection(): bool
    {
        return $this->acceptedDataProtection;
    }

    public function setAcceptedDataProtection(bool $acceptedDataProtection): void
    {
        $this->acceptedDataProtection = $acceptedDataProtection;
    }
}
