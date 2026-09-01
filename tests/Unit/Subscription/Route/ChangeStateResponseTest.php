<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\Route\ChangeStateResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeStateResponse::class)]
final class ChangeStateResponseTest extends TestCase
{
    public function testStoreApiPayloadNamesTheSubscriptionAndTheAppliedAction(): void
    {
        $response = new ChangeStateResponse('sub-1', 'pause');

        $this->assertSame(
            [
                'success' => true,
                'subscriptionId' => 'sub-1',
                'action' => 'pause',
            ],
            $response->getObject()->all()
        );
    }

    public function testApiAliasIdentifiesTheChangeStateResponse(): void
    {
        $response = new ChangeStateResponse('sub-1', 'resume');

        $this->assertSame('mollie_payments_subscription_change_state', $response->getObject()->getApiAlias());
    }
}
