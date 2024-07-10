<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Models;

class ApplePayDirectDomainAllowListItem
{
    /**
     * @var string
     */
    private $value;

    /**
     * ApplePayDirectDomainAllowListItem constructor.
     *
     * @param string $value
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Create a new ApplePayDirectDomainAllowListItem
     *
     * @param string $value
     * @return self
     */
    public static function create(string $value): self
    {
        if (empty($value)) {
            throw new \InvalidArgumentException(sprintf('The value of %s must not be empty', self::class));
        }

        # we need to have a protocol before the parse url command
        # in order to have it work correctly
        if (strpos($value, 'http') !== 0) {
            $value = 'https://' . $value;
        }

        # now extract the raw domain without protocol
        # and without any sub shop urls
        $value = (string)parse_url($value, PHP_URL_HOST);

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
