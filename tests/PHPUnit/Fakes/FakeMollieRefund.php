<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;

use Mollie\Api\Resources\Refund;

final class FakeMollieRefund extends Refund
{
    public function __construct(string $status)
    {
        $this->status = $status;
    }
}
