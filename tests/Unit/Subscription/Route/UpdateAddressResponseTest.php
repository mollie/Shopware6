<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\Route\UpdateAddressResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateAddressResponse::class)]
final class UpdateAddressResponseTest extends TestCase
{
    public function testStoreApiPayloadNamesTheUpdatedAddressAndItsType(): void
    {
        $response = new UpdateAddressResponse('sub-1', 'address-1', 'shipping');

        $this->assertSame(
            [
                'success' => true,
                'subscriptionId' => 'sub-1',
                'addressId' => 'address-1',
                'type' => 'shipping',
            ],
            $response->getObject()->all()
        );
    }

    public function testApiAliasIdentifiesTheAddressUpdateResponse(): void
    {
        $response = new UpdateAddressResponse('sub-1', 'address-1', 'billing');

        $this->assertSame('mollie_payments_subscription_address_update', $response->getObject()->getApiAlias());
    }
}
