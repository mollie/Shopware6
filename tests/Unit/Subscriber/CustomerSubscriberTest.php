<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\CustomerSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

/**
 * The Mollie customer ids live in the customer's custom fields. One-click payments and
 * subscriptions read them from this extension, so a customer loaded without it silently loses
 * their stored mandates.
 */
#[CoversClass(CustomerSubscriber::class)]
final class CustomerSubscriberTest extends TestCase
{
    private const PROFILE_ID = 'pfl_1';

    public function testEveryLoadedCustomerIsHydrated(): void
    {
        $this->assertArrayHasKey(
            CustomerEvents::CUSTOMER_LOADED_EVENT,
            CustomerSubscriber::getSubscribedEvents()
        );
    }

    public function testTheMollieCustomerIdsAreReadFromTheCustomFields(): void
    {
        $customer = $this->customer([
            'customer_ids' => [self::PROFILE_ID => [Mode::TEST->value => 'cst_1']],
        ]);

        (new CustomerSubscriber())->onCustomerLoaded($this->event($customer));

        $extension = $customer->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Customer::class, $extension);
        $this->assertSame('cst_1', $extension->getForProfileId(self::PROFILE_ID, Mode::TEST));
    }

    public function testACustomerWithoutMollieCustomFieldsIsLeftAlone(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-1');
        $customer->setCustomFields(['other_plugin' => ['foo' => 'bar']]);

        (new CustomerSubscriber())->onCustomerLoaded($this->event($customer));

        $this->assertFalse($customer->hasExtension(Mollie::EXTENSION));
    }

    /**
     * The custom field block exists on every customer that ever paid with Mollie, but it only
     * carries customer ids once Mollie created one.
     */
    public function testACustomerWithoutStoredCustomerIdsIsLeftAlone(): void
    {
        $customer = $this->customer(['something_else' => true]);

        (new CustomerSubscriber())->onCustomerLoaded($this->event($customer));

        $this->assertFalse($customer->hasExtension(Mollie::EXTENSION));
    }

    public function testAnExtensionThatIsAlreadyThereIsNotReplaced(): void
    {
        $customer = $this->customer([
            'customer_ids' => [self::PROFILE_ID => [Mode::TEST->value => 'cst_from_custom_fields']],
        ]);
        $alreadyLoaded = new Customer();
        $customer->addExtension(Mollie::EXTENSION, $alreadyLoaded);

        (new CustomerSubscriber())->onCustomerLoaded($this->event($customer));

        $this->assertSame($alreadyLoaded, $customer->getExtension(Mollie::EXTENSION));
    }

    /**
     * @param array<string, mixed> $mollieCustomFields
     */
    private function customer(array $mollieCustomFields): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-1');
        $customer->setCustomFields([Mollie::EXTENSION => $mollieCustomFields]);

        return $customer;
    }

    /**
     * @return EntityLoadedEvent<CustomerEntity>
     */
    private function event(CustomerEntity $customer): EntityLoadedEvent
    {
        return new EntityLoadedEvent(new CustomerDefinition(), [$customer], Context::createDefaultContext());
    }
}
