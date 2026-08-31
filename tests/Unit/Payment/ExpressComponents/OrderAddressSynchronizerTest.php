<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\OrderAddressSynchronizer;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * The address the shopper picked inside the wallet is the one that counts, and an order keeps its
 * own copies of the addresses. Without this sync the shop ships to the address the account had
 * before the express checkout.
 */
#[CoversClass(OrderAddressSynchronizer::class)]
final class OrderAddressSynchronizerTest extends TestCase
{
    private FakeOrderRepository $orderRepository;
    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->orderRepository = new FakeOrderRepository();
        $this->logger = new FakeLogger();
    }

    public function testNothingIsWrittenWithoutACustomer(): void
    {
        $this->synchronizer()->sync($this->order(), new FakeSalesChannelContext());

        $this->assertSame(0, $this->orderRepository->getUpdateCount());
    }

    public function testACustomerWithoutAddressesIsReportedInsteadOfWritten(): void
    {
        $this->synchronizer()->sync($this->order(), $this->context(CustomerBuilder::create()->build()));

        $this->assertSame(0, $this->orderRepository->getUpdateCount());
        $this->assertTrue($this->logger->hasRecordThatContains(LogLevel::WARNING, 'the customer has none'));
    }

    public function testTheAddressOfTheWalletIsWrittenOntoTheOrder(): void
    {
        $address = $this->address('wallet-address-id', 'Wallet Street 1', 'Wallet City');

        $this->synchronizer()->sync($this->order(), $this->context($this->customerWith($address, $address)));

        $orderAddress = $this->orderRepository->getLastUpdate()['addresses'][0];
        $this->assertSame('Wallet Street 1', $orderAddress['street']);
        $this->assertSame('Wallet City', $orderAddress['city']);
        $this->assertSame('country-id', $orderAddress['countryId']);
    }

    /**
     * A new order address row is written instead of the existing one being changed, so the
     * addresses of the original attempt stay readable in the order history.
     */
    public function testTheOrderAddressIsANewRowAndNotTheCustomerAddress(): void
    {
        $address = $this->address('wallet-address-id', 'Wallet Street 1', 'Wallet City');

        $this->synchronizer()->sync($this->order(), $this->context($this->customerWith($address, $address)));

        $update = $this->orderRepository->getLastUpdate();
        $this->assertNotSame('wallet-address-id', $update['billingAddressId']);
        $this->assertSame($update['billingAddressId'], $update['addresses'][0]['id']);
    }

    public function testOneAddressForBillingAndShippingStaysOneOrderAddress(): void
    {
        $address = $this->address('wallet-address-id', 'Wallet Street 1', 'Wallet City');

        $this->synchronizer()->sync($this->order(), $this->context($this->customerWith($address, $address)));

        $update = $this->orderRepository->getLastUpdate();
        $this->assertCount(1, $update['addresses']);
        $this->assertSame($update['billingAddressId'], $update['deliveries'][0]['shippingOrderAddressId']);
    }

    public function testDifferentBillingAndShippingAddressesBecomeTwoOrderAddresses(): void
    {
        $customer = $this->customerWith(
            $this->address('billing-address-id', 'Billing Street 1', 'Billing City'),
            $this->address('shipping-address-id', 'Shipping Street 2', 'Shipping City')
        );

        $this->synchronizer()->sync($this->order(), $this->context($customer));

        $update = $this->orderRepository->getLastUpdate();
        $this->assertCount(2, $update['addresses']);
        $this->assertSame('Billing City', $update['addresses'][0]['city']);
        $this->assertSame('Shipping City', $update['addresses'][1]['city']);
    }

    public function testTheDeliveryIsMovedToTheShippingAddress(): void
    {
        $customer = $this->customerWith(
            $this->address('billing-address-id', 'Billing Street 1', 'Billing City'),
            $this->address('shipping-address-id', 'Shipping Street 2', 'Shipping City')
        );

        $this->synchronizer()->sync($this->order(), $this->context($customer));

        $update = $this->orderRepository->getLastUpdate();
        $this->assertSame('order-delivery-id', $update['deliveries'][0]['id']);
        $this->assertSame($update['addresses'][1]['id'], $update['deliveries'][0]['shippingOrderAddressId']);
        $this->assertNotSame($update['billingAddressId'], $update['deliveries'][0]['shippingOrderAddressId']);
    }

    public function testAnOrderWithoutDeliveriesIsWrittenWithoutThem(): void
    {
        $address = $this->address('wallet-address-id', 'Wallet Street 1', 'Wallet City');
        $order = $this->order();
        $order->setDeliveries(new OrderDeliveryCollection());

        $this->synchronizer()->sync($order, $this->context($this->customerWith($address, $address)));

        $this->assertArrayNotHasKey('deliveries', $this->orderRepository->getLastUpdate());
    }

    /**
     * billingAddressId is write protected for a sales channel context, so the update only goes
     * through in the system scope.
     */
    public function testTheOrderIsWrittenInTheSystemScope(): void
    {
        $address = $this->address('wallet-address-id', 'Wallet Street 1', 'Wallet City');

        $this->synchronizer()->sync($this->order(), $this->context($this->customerWith($address, $address)));

        $this->assertSame(Context::SYSTEM_SCOPE, $this->orderRepository->getLastUpdateScope());
    }

    private function synchronizer(): OrderAddressSynchronizer
    {
        return new OrderAddressSynchronizer($this->orderRepository, $this->logger);
    }

    private function order(): OrderEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId('order-delivery-id');

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        return $order;
    }

    private function context(CustomerEntity $customer): FakeSalesChannelContext
    {
        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);

        return $context;
    }

    private function customerWith(CustomerAddressEntity $billingAddress, CustomerAddressEntity $shippingAddress): CustomerEntity
    {
        return CustomerBuilder::create()
            ->withActiveBillingAddress($billingAddress)
            ->withActiveShippingAddress($shippingAddress)
            ->build()
        ;
    }

    private function address(string $id, string $street, string $city): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId($id);
        $address->setSalutationId('salutation-id');
        $address->setFirstName('Test');
        $address->setLastName('Customer');
        $address->setStreet($street);
        $address->setZipcode('12345');
        $address->setCity($city);
        $address->setCountryId('country-id');

        return $address;
    }
}
