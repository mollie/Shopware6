<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Mollie;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentDetails;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use PHPUnit\Framework\TestCase;

class MolliePaymentDetailsTest extends TestCase
{
    /**
     * This test verifies that an existing mandate id
     * is correctly found and returned
     */
    public function testMandateIDFound(): void
    {
        $fakePayment = new Payment(new MollieApiClient());
        $fakePayment->mandateId = 'mdt_123';

        $details = new MolliePaymentDetails();
        $mandateId = $details->getMandateId($fakePayment);

        $this->assertEquals('mdt_123', $mandateId);
    }

    /**
     * This test verifies that we get an empty string
     * if no mandate ID is found. So we unset the property
     * in our stdClass object before we start the test.
     */
    public function testMandateIDNotFound(): void
    {
        $fakePayment = new Payment(new MollieApiClient());
        unset($fakePayment->mandateId);

        $details = new MolliePaymentDetails();
        $mandateId = $details->getMandateId($fakePayment);

        $this->assertEquals('', $mandateId);
    }

    /**
     * This test verifies that we get an empty string
     * if no payment is existing and provided for the function.
     * This needs to exist for the fail-safe approach in the webhooks
     */
    public function testMandateEmptyWithoutPayment(): void
    {
        $details = new MolliePaymentDetails();
        $mandateId = $details->getMandateId(null);

        $this->assertEquals('', $mandateId);
    }
}
