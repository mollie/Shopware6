<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Models;

use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayDirectDomainSanitizer;

class ApplePayDirectDomainAllowListItem
{
    /**
     * @var string
     */
    private $value;

    /**
     * ApplePayDirectDomainAllowListItem constructor.
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Create a new ApplePayDirectDomainAllowListItem
     */
    public static function create(string $value): self
    {
        if (empty($value)) {
            throw new \InvalidArgumentException(sprintf('The value of %s must not be empty', self::class));
        }

        $value = (new ApplePayDirectDomainSanitizer())->sanitizeDomain($value);

        return new self($value);
    }

    /**
     * Compare the value with the given value
     *
     * @param string $value value that will be compared
     */
    public function equals(string $value): bool
    {
        return $this->value === $value;
    }
}
