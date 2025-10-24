<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mollie;

use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Shopware\Core\Checkout\Order\OrderEntity;

interface RequestFactoryInterface
{
    public function createPayment(OrderEntity $order): CreatePaymentRequest;
}
