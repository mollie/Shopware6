<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressMethod;

use Mollie\Shopware\Component\Payment\ExpressMethod\TempAddress;
use Mollie\Shopware\Component\Payment\ExpressMethod\TempAddressManager;
use Mollie\Shopware\Unit\Fake\CustomerEntityBuilder;
use Mollie\Shopware\Unit\Fake\FakeContextSwitchRoute;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

#[CoversClass(TempAddressManager::class)]
final class TempAddressManagerTest extends TestCase
{
    public function testApplyPointsTheContextAtTheTempAddress(): void
    {
        $customer = $this->customer('address-shipping', 'address-billing');
        $manager = new TempAddressManager(new FakeContextSwitchRoute(), $this->addressRepository());

        $requestDataBag = $manager->apply(new RequestDataBag(), new TempAddress($customer, 'country-de'), $this->context($customer));

        $this->assertSame(TempAddress::getId($customer), $requestDataBag->get(SalesChannelContextService::SHIPPING_ADDRESS_ID));
        $this->assertSame(TempAddress::getId($customer), $requestDataBag->get(SalesChannelContextService::BILLING_ADDRESS_ID));
    }

    public function testApplyWritesTheTempAddress(): void
    {
        $customer = $this->customer('address-shipping', 'address-billing');
        $addressRepository = $this->addressRepository();
        $manager = new TempAddressManager(new FakeContextSwitchRoute(), $addressRepository);

        $manager->apply(new RequestDataBag(), new TempAddress($customer, 'country-de'), $this->context($customer));

        $this->assertSame(TempAddress::getId($customer), $addressRepository->data[0][0]['id']);
    }

    public function testRestoreSwitchesBackToTheAddressesTheShopperHadActive(): void
    {
        $customer = $this->customer('address-shipping', 'address-billing');
        $contextSwitchRoute = new FakeContextSwitchRoute();
        $manager = new TempAddressManager($contextSwitchRoute, $this->addressRepository());

        $manager->restore($this->context($customer));

        $this->assertSame(
            [[
                SalesChannelContextService::SHIPPING_ADDRESS_ID => 'address-shipping',
                SalesChannelContextService::BILLING_ADDRESS_ID => 'address-billing',
            ]],
            $contextSwitchRoute->getSwitches()
        );
    }

    public function testRestoreFallsBackToTheDefaultAddressWhenTheTempAddressIsStillActive(): void
    {
        $customer = $this->customer('address-shipping', 'address-billing');
        $customer->setActiveShippingAddress($this->address(TempAddress::getId($customer)));
        $contextSwitchRoute = new FakeContextSwitchRoute();
        $manager = new TempAddressManager($contextSwitchRoute, $this->addressRepository());

        $manager->restore($this->context($customer));

        $this->assertSame(
            [[
                SalesChannelContextService::SHIPPING_ADDRESS_ID => 'default-shipping',
                SalesChannelContextService::BILLING_ADDRESS_ID => 'address-billing',
            ]],
            $contextSwitchRoute->getSwitches()
        );
    }

    public function testRestoreDeletesTheTempAddress(): void
    {
        $customer = $this->customer('address-shipping', 'address-billing');
        $addressRepository = $this->addressRepository();
        $manager = new TempAddressManager(new FakeContextSwitchRoute(), $addressRepository);

        $manager->restore($this->context($customer));

        $this->assertSame([[['id' => TempAddress::getId($customer)]]], $addressRepository->data);
    }

    public function testRestoreSwitchesTheContextBeforeTheTempAddressIsDeleted(): void
    {
        $customer = $this->customer('address-shipping', 'address-billing');
        $contextSwitchRoute = new FakeContextSwitchRoute();
        $addressRepository = new class($contextSwitchRoute) extends FakeEntityRepository {
            public ?int $switchesBeforeDelete = null;

            public function __construct(private readonly FakeContextSwitchRoute $contextSwitchRoute)
            {
                parent::__construct(new CustomerAddressDefinition());
            }

            public function delete(array $data, Context $context): EntityWrittenContainerEvent
            {
                $this->switchesBeforeDelete = \count($this->contextSwitchRoute->getSwitches());

                return parent::delete($data, $context);
            }
        };
        $addressRepository->entityWrittenContainerEvents[] = $this->writtenEvent();
        $manager = new TempAddressManager($contextSwitchRoute, $addressRepository);

        $manager->restore($this->context($customer));

        $this->assertSame(1, $addressRepository->switchesBeforeDelete);
    }

    public function testRestoreLeavesAGuestContextUntouched(): void
    {
        $contextSwitchRoute = new FakeContextSwitchRoute();
        $addressRepository = $this->addressRepository();
        $manager = new TempAddressManager($contextSwitchRoute, $addressRepository);

        $manager->restore(new FakeSalesChannelContext());

        $this->assertSame([], $contextSwitchRoute->getSwitches());
        $this->assertSame([], $addressRepository->data);
    }

    private function customer(string $activeShippingAddressId, string $activeBillingAddressId): CustomerEntity
    {
        $customer = (new CustomerEntityBuilder())->getDefaultCustomer();
        $customer->setDefaultShippingAddressId('default-shipping');
        $customer->setDefaultBillingAddressId('default-billing');
        $customer->setActiveShippingAddress($this->address($activeShippingAddressId));
        $customer->setActiveBillingAddress($this->address($activeBillingAddressId));

        return $customer;
    }

    private function addressRepository(): FakeEntityRepository
    {
        $addressRepository = new FakeEntityRepository(new CustomerAddressDefinition());
        $addressRepository->entityWrittenContainerEvents[] = $this->writtenEvent();

        return $addressRepository;
    }

    private function writtenEvent(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
    }

    private function address(string $id): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId($id);

        return $address;
    }

    private function context(CustomerEntity $customer): FakeSalesChannelContext
    {
        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);

        return $context;
    }
}
