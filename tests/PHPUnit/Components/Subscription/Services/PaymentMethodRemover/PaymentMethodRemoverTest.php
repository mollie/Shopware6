<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\Subscription\Services\PaymentMethodRemover;

use Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover\SubscriptionRemover;
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
            'directdebit',
            'trustly',
        ];

        $this->assertEquals($expected, SubscriptionRemover::ALLOWED_METHODS);
    }
}
