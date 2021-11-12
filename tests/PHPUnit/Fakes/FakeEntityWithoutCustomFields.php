<?php

namespace MolliePayments\Tests\Fakes;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Uuid\Uuid;

class FakeEntityWithoutCustomFields extends Entity
{
    use EntityIdTrait;

    public function __construct()
    {
        $this->setId(Uuid::randomHex());
    }
}
