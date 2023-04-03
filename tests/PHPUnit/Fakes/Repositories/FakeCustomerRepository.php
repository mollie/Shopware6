<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes\Repositories;

use Kiener\MolliePayments\Repository\Customer\CustomerRepositoryInterface;
use MolliePayments\Tests\Fakes\FakeEntityRepository;

class FakeCustomerRepository extends FakeEntityRepository implements CustomerRepositoryInterface
{

}
