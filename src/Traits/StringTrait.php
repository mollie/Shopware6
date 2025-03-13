<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Traits;

trait StringTrait
{
    protected function stringStartsWith(string $haystack, string $needle): bool
    {
        if (strpos($haystack, $needle) === 0) {
            return true;
        }

        return false;
    }

    protected function stringContains(string $haystack, string $needle): bool
    {
        if (strpos($haystack, $needle) !== false) {
            return true;
        }

        return false;
    }
}
