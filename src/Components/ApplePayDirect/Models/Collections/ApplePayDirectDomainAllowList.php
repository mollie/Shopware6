<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Models\Collections;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayDirectDomainAllowListItem;

class ApplePayDirectDomainAllowList implements \Countable
{
    /**
     * @var ApplePayDirectDomainAllowListItem[]
     */
    private $allowList;

    /**
     * ApplePayDirectDomainAllowList constructor.
     *
     * @param ApplePayDirectDomainAllowListItem[] $allowList
     */
    private function __construct(array $allowList = [])
    {
        $this->allowList = $allowList;
    }

    /**
     * Create a new ApplePayDirectDomainAllowList
     */
    public static function create(ApplePayDirectDomainAllowListItem ...$items): self
    {
        return new self($items);
    }

    /**
     * Check if the given value is in the allow list
     */
    public function contains(string $value): bool
    {
        foreach ($this->allowList as $item) {
            if ($item->equals($value)) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return count($this) === 0;
    }

    public function count(): int
    {
        return count($this->allowList);
    }
}
