<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Mollie;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use PHPUnit\Framework\TestCase;

class MolliePaymentStatusTest extends TestCase
{
    /**
     * This test verifies that our approved verification has the
     * correct list of valid payment status entry in it.
     * It is used to define what payment status entries are valid and
     * thus can be continued to finish a successful order in Shopware.
     *
     * @dataProvider getIsApprovedData
     */
    public function testApprovedStatus(bool $expected, string $status): void
    {
        $isApproved = MolliePaymentStatus::isApprovedStatus($status);

        $this->assertEquals($expected, $isApproved);
    }

    /**
     * @return array[]
     */
    public function getIsApprovedData()
    {
        return [
            [true, MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED],
            [true, MolliePaymentStatus::MOLLIE_PAYMENT_PENDING],
            [true, MolliePaymentStatus::MOLLIE_PAYMENT_PAID],
            [true, MolliePaymentStatus::MOLLIE_PAYMENT_OPEN],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_FAILED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_UNKNOWN],
        ];
    }

    /**
     * This test verifies that our failed payment can be successfully recognized.
     * Failed means, that no valid order has been created, and the whole
     * process should get aborted or cancelled.
     *
     * @dataProvider getIsFailedData
     */
    public function testFailedStatus(bool $expected, string $status): void
    {
        $isApproved = MolliePaymentStatus::isFailedStatus('', $status);

        $this->assertEquals($expected, $isApproved);
    }

    /**
     * @return array[]
     */
    public function getIsFailedData()
    {
        return [
            [true, MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED],
            [true, MolliePaymentStatus::MOLLIE_PAYMENT_FAILED],
            [true, MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_OPEN],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_PENDING],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_PAID],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED],
            [false, MolliePaymentStatus::MOLLIE_PAYMENT_UNKNOWN],
        ];
    }

    /**
     * This test verifies that open credit cards are not approved as valid payment.
     * We don't know when this happens, but the payment has still "created" and will be expired
     * after 15 minutes by Mollie. If we show a success, then nobody would recognize that it didnt work
     * and so its expired and leads to a cancelled order.
     */
    public function testOpenCreditCardFails(): void
    {
        $isApproved = MolliePaymentStatus::isFailedStatus('creditcard', MolliePaymentStatus::MOLLIE_PAYMENT_OPEN);

        $this->assertEquals(true, $isApproved);
    }
}
