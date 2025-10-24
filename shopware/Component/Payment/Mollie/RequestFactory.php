<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mollie;

use Mollie\Api\Http\Data\Money;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Shopware\Core\Checkout\Order\OrderEntity;

final class RequestFactory implements RequestFactoryInterface
{
    public function createPayment(OrderEntity $order): CreatePaymentRequest
    {
        return new CreatePaymentRequest($order->getOrderNumber(), new Money($order->getCurrency()->getIsoCode(), (string) $order->getAmountNet()));
    }
}
