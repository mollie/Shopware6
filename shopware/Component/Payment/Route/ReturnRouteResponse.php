<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

final class ReturnRouteResponse extends StoreApiResponse
{
    public function __construct(private Payment $payment)
    {
        parent::__construct(new ArrayStruct(
            [
                'paymentId' => $this->payment->getId(),
                'status' => $this->payment->getStatus(),
                'orderId' => $this->payment->getShopwareTransaction()->getOrderId(),
                'finalizeUrl' => $this->payment->getMollieTransaction()->getFinalizeUrl(),
            ],
            'mollie_payment_return_response'
        ));
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->payment->getStatus();
    }

    public function getPaymentId(): string
    {
        return $this->payment->getId();
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    /**
     * @return mixed
     */
    public function getOrderId()
    {
        return $this->payment->getShopwareTransaction()->getOrderId();
    }

    public function getFinalizeUrl(): string
    {
        return $this->payment->getMollieTransaction()->getFinalizeUrl();
    }
}
