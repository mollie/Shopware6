<?php

namespace MolliePayments\Tests\Fakes;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Uuid\Uuid;

class FakeEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    public function __construct()
    {
        $this->setId(Uuid::randomHex());
    }
}
