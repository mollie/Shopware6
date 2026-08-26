<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\Route\UpdatePaymentMethodConfirmedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdatePaymentMethodConfirmedResponse::class)]
final class UpdatePaymentMethodConfirmedResponseTest extends TestCase
{
    public function testStoreApiPayloadConfirmsTheUpdateForTheSubscription(): void
    {
        $response = new UpdatePaymentMethodConfirmedResponse('sub-1');

        $this->assertSame(
            [
                'success' => true,
                'subscriptionId' => 'sub-1',
            ],
            $response->getObject()->all()
        );
    }

    public function testApiAliasIdentifiesTheConfirmedPaymentMethodUpdate(): void
    {
        $response = new UpdatePaymentMethodConfirmedResponse('sub-1');

        $this->assertSame('mollie_payments_subscription_payment_update_confirm', $response->getObject()->getApiAlias());
    }
}
