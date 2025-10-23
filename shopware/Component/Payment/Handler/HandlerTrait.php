<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Payment\Action\Pay;

trait HandlerTrait
{
    private Pay $pay;

    public function __construct(Pay $pay)
    {
        $this->pay = $pay;
    }
}