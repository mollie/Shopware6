<?php

namespace MolliePayments\Tests\Service\Mollie;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use PHPUnit\Framework\TestCase;

class OrderStatusConverterTest extends TestCase
{
    /** @var OrderStatusConverter */
    private $statusConverter;

    /** @var Order */
    private $order;

    /** @var Payment */
    private $payment;

    protected function setUp(): void
    {
        $this->payment = $this->createMock(Payment::class);
        $this->payment->createdAt = date(DATE_ISO8601);

        $fakePayment = $this->createMock(Payment::class);
        $fakePayment->createdAt = date(DATE_ISO8601, strtotime('-1 hour'));

        $paymentCollection = new PaymentCollection($this->createMock(MollieApiClient::class), 2, null);
        $paymentCollection->append($this->payment);
        $paymentCollection->append($fakePayment);

        $this->order = $this->createConfiguredMock(Order::class, [
            'payments' => $paymentCollection
        ]);

        $this->statusConverter = new OrderStatusConverter();
    }

    public function testChargebackStatus()
    {
        $this->payment->amountChargedBack = (object)[
            'value' => 9.99,
            'currency' => 'EUR'
        ];

        $actualOrderStatus = $this->statusConverter->getMollieOrderStatus($this->order);
        $actualPaymentStatus = $this->statusConverter->getMolliePaymentStatus($this->payment);

        $this->assertEquals(MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK, $actualOrderStatus);
        $this->assertEquals(MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK, $actualPaymentStatus);
    }
}
