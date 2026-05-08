<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Data;

trait ShopwareTestBehaviour
{
    public function getName(): string
    {
        return (new \ReflectionClass($this))->getName();
    }
}
