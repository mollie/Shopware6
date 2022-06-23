<?php


namespace MolliePayments\Tests\Components\Subscription\Services\PaymentMethodRemover;

use Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover\PaymentMethodRemover;
use PHPUnit\Framework\TestCase;

class PaymentMethodRemoverTest extends TestCase
{

    /**
     * This test verifies our allowed payment methods.
     * They are used to filter the existing list of available
     * payment methods.
     *
     * @return void
     */
    public function testAllowedPaymentMethods()
    {
        $expected = [
            'ideal',
            'bancontact',
            'sofort',
            'eps',
            'giropay',
            'belfius',
            'creditcard',
            'paypal',
        ];

        $this->assertEquals($expected, PaymentMethodRemover::ALLOWED_METHODS);
    }

}
