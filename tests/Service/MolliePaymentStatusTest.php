<?php

namespace Kiener\MolliePayments\Tests\Service;


use Kiener\MolliePayments\Service\MolliePaymentStatus;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MolliePaymentStatusTest extends TestCase
{

    public function testThatCorrectPaymentStatusIsExtracted(): void
    {
        $paymentDateFirst = "2021-02-07T12:01:00+00:00";
        $paymentDateSecond = "2021-02-07T12:02:00+00:00";
        $paymentDateThird = "2021-02-07T12:02:10+00:00";

        $mollieApiClient = $this->getMockBuilder(MollieApiClient::class)->disableOriginalConstructor()->getMock();
        $first = new Payment($mollieApiClient);
        $first->createdAt = $paymentDateFirst;
        $first->status = PaymentStatus::STATUS_OPEN;
        $second = new Payment($mollieApiClient);
        $second->createdAt = $paymentDateSecond;
        $second->status = PaymentStatus::STATUS_FAILED;
        $third = new Payment($mollieApiClient);
        $third->createdAt = $paymentDateThird;
        $third->status = PaymentStatus::STATUS_PAID;

        $paymentArray = [$first, $third, $second];

        $service = new MolliePaymentStatus();

        $this->assertSame(PaymentStatus::STATUS_PAID, $service->getCurrentPaymentStatus($paymentArray));
    }
}
