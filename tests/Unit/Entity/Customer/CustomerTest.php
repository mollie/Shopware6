<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\Customer;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Entity\Customer\Customer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Customer::class)]
final class CustomerTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $customer = new Customer();

        $this->assertSame([], $customer->getCustomerIds());
        $this->assertNull($customer->getForProfileId('profile-1', Mode::LIVE));
    }

    public function testSetAndGetCustomerId(): void
    {
        $customer = new Customer();
        $customer->setCustomerId('profile-1', Mode::LIVE, 'cst_live_abc');

        $this->assertSame('cst_live_abc', $customer->getForProfileId('profile-1', Mode::LIVE));
    }

    public function testLiveAndTestModeAreStoredSeparately(): void
    {
        $customer = new Customer();
        $customer->setCustomerId('profile-1', Mode::LIVE, 'cst_live_abc');
        $customer->setCustomerId('profile-1', Mode::TEST, 'cst_test_xyz');

        $this->assertSame('cst_live_abc', $customer->getForProfileId('profile-1', Mode::LIVE));
        $this->assertSame('cst_test_xyz', $customer->getForProfileId('profile-1', Mode::TEST));
    }

    public function testMultipleProfilesAreStoredSeparately(): void
    {
        $customer = new Customer();
        $customer->setCustomerId('profile-A', Mode::LIVE, 'cst_A_live');
        $customer->setCustomerId('profile-B', Mode::LIVE, 'cst_B_live');

        $this->assertSame('cst_A_live', $customer->getForProfileId('profile-A', Mode::LIVE));
        $this->assertSame('cst_B_live', $customer->getForProfileId('profile-B', Mode::LIVE));
        $this->assertNull($customer->getForProfileId('profile-A', Mode::TEST));
    }

    public function testToArray(): void
    {
        $customer = new Customer();
        $customer->setCustomerId('profile-1', Mode::LIVE, 'cst_live_abc');

        $array = $customer->toArray();

        $this->assertArrayHasKey('customer_ids', $array);
        $this->assertSame('cst_live_abc', $array['customer_ids']['profile-1'][Mode::LIVE->value]);
    }

    public function testConstructorAcceptsExistingCustomerIds(): void
    {
        $ids = ['profile-1' => ['live' => 'cst_existing']];
        $customer = new Customer($ids);

        $this->assertSame('cst_existing', $customer->getForProfileId('profile-1', Mode::LIVE));
    }
}
