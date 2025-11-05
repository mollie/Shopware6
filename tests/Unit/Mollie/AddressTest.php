<?php
declare(strict_types=1);

namespace Mollie\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Unit\Mollie\Fake\FakeCustomerRepository;
use Mollie\Unit\Mollie\Fake\FakeOrderRepository;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;


final class AddressTest extends TestCase
{
    private FakeCustomerRepository $customerRepository;
    private FakeOrderRepository $orderRepository;

    public function setUp(): void
    {
        $this->customerRepository = new FakeCustomerRepository();
        $this->orderRepository = new FakeOrderRepository();
    }

    public function testCanCreateFromEntity(): void
    {

        $customer = $this->customerRepository->getDefaultOrderCustomer();
        $orderAddress = $this->orderRepository->getOrderAddress($customer);
        $orderAddress->setPhoneNumber('+1234567890');
        $orderAddress->setAdditionalAddressLine1('Appartment 2');
        $orderAddress->setAdditionalAddressLine2('Block C');
        $orderAddress->setCompany('Test Company');
        $actual = Address::fromAddress($customer, $orderAddress);
        $expected = [
            'title' => 'Not specified',
            'givenName' => 'Tester',
            'familyName' => 'Test',
            'organizationName' => 'Test Company',
            'streetAndNumber' => 'Test Street',
            'streetAdditional' => 'Appartment 2 Block C',
            'postalCode' => '12345',
            'email' => 'fake@unit.test',
            'phone' => '+1234567890',
            'city' => 'Test City',
            'country' => 'DE',
        ];
        $this->assertInstanceOf(Address::class, $actual);

        $this->assertSame($expected['givenName'], $actual->getGivenName());
        $this->assertSame($expected['familyName'], $actual->getFamilyName());
        $this->assertSame($expected['organizationName'], $actual->getOrganizationName());
        $this->assertSame($expected['streetAndNumber'], $actual->getStreetAndNumber());
        $this->assertSame($expected['streetAdditional'], $actual->getStreetAdditional());
        $this->assertSame($expected['phone'], $actual->getPhone());
        $this->assertSame($expected['postalCode'], $actual->getPostalCode());
        $this->assertSame($expected['email'], $actual->getEmail());
        $this->assertSame($expected['city'], $actual->getCity());
        $this->assertSame($expected['country'], $actual->getCountry());
        $this->assertSame($expected['title'], $actual->getTitle());
    }

    public function testExpectExceptionOnEmptyCustomer():void
    {
        $this->expectErrorMessage('Customer cannot be null');
        $orderAddress = new OrderAddressEntity();
        $actual = Address::fromAddress(null, $orderAddress);
    }
    public function testExpectExceptionOnEmptyOrderAddress(){
        $this->expectErrorMessage('Address should not be null');
        $customer = new OrderCustomerEntity();
        $actual = Address::fromAddress($customer, null);
    }
}