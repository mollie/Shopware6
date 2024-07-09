<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Models;

class ApplePayValidationUrlAllowListItem
{
    /**
     * @var string
     */
    private $value;

    /**
     * ApplePayValidationUrlAllowListItem constructor.
     *
     * @param string $value
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function create(string $value): self
    {
        if (empty($value)) {
            throw new \InvalidArgumentException(sprintf('The value of %s must not be empty', self::class));
        }

        if (strpos($value, 'http') !== 0) {
            $value = 'https://' . $value;
        }

        if (substr($value, -1) !== '/') {
            $value .= '/';
        }

        return new self($value);
    }

    /**
     * Compare the value with the given value
     *
     * @param string $value value that will be compared
     * @return bool
     */
    public function equals(string $value): bool
    {
        return $this->value === $value;
    }
}
