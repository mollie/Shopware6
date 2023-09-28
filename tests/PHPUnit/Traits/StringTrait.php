<?php

namespace MolliePayments\Tests\Traits;

trait StringTrait
{

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    protected function stringStartsWith(string $haystack, string $needle): bool
    {
        if (strpos($haystack, $needle) === 0) {
            return true;
        }

        return false;
    }

}
