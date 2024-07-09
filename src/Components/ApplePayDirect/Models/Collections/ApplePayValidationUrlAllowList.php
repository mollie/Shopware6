<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Models\Collections;

use Countable;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayValidationUrlAllowListItem;

class ApplePayValidationUrlAllowList implements Countable
{
    /**
     * @var ApplePayValidationUrlAllowListItem[]
     */
    private $allowList;

    /**
     * ApplePayValidationUrlAllowList constructor.
     *
     * @param ApplePayValidationUrlAllowListItem[] $allowList
     */
    private function __construct(array $allowList = [])
    {
        $this->allowList = $allowList;
    }

    /**
     * Create a new ApplePayAllowList
     *
     * @param ApplePayValidationUrlAllowListItem ...$items
     * @return ApplePayValidationUrlAllowList
     */
    public static function create(ApplePayValidationUrlAllowListItem ...$items): self
    {
        return new self($items);
    }

    /**
     * Check if the given value is in the allow list
     *
     * @param string $value
     * @return bool
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

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this) === 0;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->allowList);
    }
}
