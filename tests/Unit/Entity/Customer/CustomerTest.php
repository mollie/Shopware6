<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\Customer;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Entity\Customer\Customer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A shop can run several Mollie profiles, and every profile has a separate customer in test and in
 * live mode. Handing a live customer id to a test payment makes Mollie reject the payment, so the
 * ids are kept apart by profile and mode.
 */
#[CoversClass(Customer::class)]
final class CustomerTest extends TestCase
{
    private const PROFILE = 'pfl_1';

    public function testTheCustomerIdOfTheProfileAndModeIsReturned(): void
    {
        $customer = new Customer();
        $customer->setCustomerId(self::PROFILE, Mode::TEST, 'cst_test');

        $this->assertSame('cst_test', $customer->getForProfileId(self::PROFILE, Mode::TEST));
    }

    public function testTestAndLiveCustomerIdsAreKeptApart(): void
    {
        $customer = new Customer();
        $customer->setCustomerId(self::PROFILE, Mode::TEST, 'cst_test');
        $customer->setCustomerId(self::PROFILE, Mode::LIVE, 'cst_live');

        $this->assertSame('cst_test', $customer->getForProfileId(self::PROFILE, Mode::TEST));
        $this->assertSame('cst_live', $customer->getForProfileId(self::PROFILE, Mode::LIVE));
    }

    public function testAnotherProfileHasItsOwnCustomerId(): void
    {
        $customer = new Customer();
        $customer->setCustomerId(self::PROFILE, Mode::TEST, 'cst_test');

        $this->assertNull($customer->getForProfileId('pfl_2', Mode::TEST));
    }

    /**
     * A customer who never paid with Mollie in that mode has no id yet - null makes the checkout
     * create one instead of sending an unknown id.
     */
    public function testACustomerWithoutAnIdForThatModeReturnsNull(): void
    {
        $customer = new Customer();
        $customer->setCustomerId(self::PROFILE, Mode::TEST, 'cst_test');

        $this->assertNull($customer->getForProfileId(self::PROFILE, Mode::LIVE));
    }

    public function testAFreshCustomerHasNoIdsAtAll(): void
    {
        $this->assertSame([], (new Customer())->getCustomerIds());
    }

    /**
     * The ids are written back into the customer's custom fields under this key; a different one
     * would lose every stored Mollie customer on the next save.
     */
    public function testTheIdsArePersistedUnderTheirCustomFieldKey(): void
    {
        $customer = new Customer();
        $customer->setCustomerId(self::PROFILE, Mode::TEST, 'cst_test');

        $this->assertSame(['customer_ids' => [self::PROFILE => ['test' => 'cst_test']]], $customer->toArray());
    }
}
