<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final class FakeCustomerRepository extends EntityRepository
{
    public function __construct()
    {
    }
}
