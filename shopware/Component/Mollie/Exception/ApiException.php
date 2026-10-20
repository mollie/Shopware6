<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Exception;

final class ApiException extends \Exception
{
    private const APPLE_PAY_DOMAIN_NOT_VERIFIED = 'not able to verify your domain';
    private const FIELD_SHIPPING_CALLBACK_URL = 'shipping.callbackUrl';

    private string $title;

    private string $details;

    private string $field;

    public function __construct(int $statusCode, string $title, string $details, string $field)
    {
        $this->title = $title;
        $this->details = $details;
        $this->field = $field;

        $message = sprintf('Error in field %s. %s: %s ', $field, $title, $details);
        parent::__construct($message, $statusCode);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDetails(): string
    {
        return $this->details;
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Mollie rejects cancelling a payment/order that is no longer in a cancellable state. This is
     * expected behaviour (not every payment method can be cancelled directly), so callers can treat
     * it as a warning instead of an error.
     */
    public function isCancellationNotPossible(): bool
    {
        return stripos($this->details, 'cannot be cancel') !== false;
    }

    public function isApplePayDomainNotVerified(): bool
    {
        return stripos($this->details, self::APPLE_PAY_DOMAIN_NOT_VERIFIED) !== false;
    }

    public function isShippingCallbackUrlRejected(): bool
    {
        return $this->field === self::FIELD_SHIPPING_CALLBACK_URL;
    }
}
