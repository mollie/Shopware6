<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\Route\UpdatePaymentMethodResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdatePaymentMethodResponse::class)]
final class UpdatePaymentMethodResponseTest extends TestCase
{
    public function testStoreApiPayloadCarriesTheCheckoutUrlTheShopperIsSentTo(): void
    {
        $response = new UpdatePaymentMethodResponse('sub-1', 'https://mollie.test/checkout');

        $this->assertSame(
            [
                'success' => true,
                'subscriptionId' => 'sub-1',
                'checkoutUrl' => 'https://mollie.test/checkout',
            ],
            $response->getObject()->all()
        );
    }

    public function testApiAliasIdentifiesTheStartOfThePaymentMethodUpdate(): void
    {
        $response = new UpdatePaymentMethodResponse('sub-1', 'https://mollie.test/checkout');

        $this->assertSame('mollie_payments_subscription_payment_update_start', $response->getObject()->getApiAlias());
    }
}
