<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\Subscription\Services\PaymentMethodRemover;

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
        $this->markTestSkipped('will be removed');
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
            'paybybank'
        ];

        $this->assertEquals($expected, SubscriptionRemover::ALLOWED_METHODS);
    }
}
