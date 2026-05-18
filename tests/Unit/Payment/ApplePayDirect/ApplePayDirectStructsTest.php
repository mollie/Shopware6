<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayAmount;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayCart;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayLineItem;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingLineItem;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingMethod;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\FakeApplePayAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(ApplePayAmount::class)]
#[CoversClass(ApplePayLineItem::class)]
#[CoversClass(ApplePayShippingLineItem::class)]
#[CoversClass(ApplePayShippingMethod::class)]
#[CoversClass(ApplePayCart::class)]
#[CoversClass(FakeApplePayAddress::class)]
final class ApplePayDirectStructsTest extends TestCase
{
    public function testApplePayAmountStoresAndReturnsValue(): void
    {
        $amount = new ApplePayAmount(9.99);

        $this->assertSame(9.99, $amount->getValue());
        $this->assertSame(9.99, $amount->jsonSerialize());
    }

    public function testApplePayLineItemStoresFields(): void
    {
        $amount = new ApplePayAmount(5.00);
        $item = new ApplePayLineItem('Product A', $amount, 'final');

        $this->assertSame('Product A', $item->getLabel());
        $this->assertSame($amount, $item->getAmount());
        $this->assertSame('final', $item->getType());
    }

    public function testApplePayLineItemDefaultTypeIsFinal(): void
    {
        $item = new ApplePayLineItem('Product B', new ApplePayAmount(3.00));

        $this->assertSame('final', $item->getType());
    }

    public function testApplePayShippingLineItemExtendsLineItem(): void
    {
        $amount = new ApplePayAmount(4.90);
        $item = new ApplePayShippingLineItem('Standard Shipping', $amount);

        $this->assertInstanceOf(ApplePayLineItem::class, $item);
        $this->assertSame('Standard Shipping', $item->getLabel());
        $this->assertSame(4.90, $item->getAmount()->getValue());
    }

    public function testApplePayShippingMethodStoresFields(): void
    {
        $amount = new ApplePayAmount(4.90);
        $method = new ApplePayShippingMethod('std', 'Standard', '3-5 days', $amount);

        $this->assertSame('std', $method->getIdentifier());
        $this->assertSame('Standard', $method->getLabel());
        $this->assertSame('3-5 days', $method->getDetail());
        $this->assertSame($amount, $method->getAmount());
    }

    public function testApplePayCartStoresLabelAndAmountAndReturnsShippingAmount(): void
    {
        $amount = new ApplePayAmount(29.99);
        $cart = new ApplePayCart('Order Total', $amount);

        $this->assertSame('mollie_payments_applepay_direct_cart', $cart->getApiAlias());

        $shippingItem = new ApplePayShippingLineItem('Shipping', new ApplePayAmount(4.90));
        $cart->addItem($shippingItem);

        $this->assertSame(4.90, $cart->getShippingAmount()->getValue());
    }

    public function testFakeApplePayAddressGeneratesConsistentId(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $idA = FakeApplePayAddress::getId($customer);
        $idB = FakeApplePayAddress::getId($customer);

        $this->assertSame($idA, $idB);
        $this->assertNotEmpty($idA);
    }

    public function testFakeApplePayAddressToUpsertArrayContainsRequiredFields(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $countryId = Uuid::randomHex();

        $address = new FakeApplePayAddress($customer, $countryId);
        $result = $address->toUpsertArray();

        $this->assertSame($countryId, $result['countryId']);
        $this->assertSame($customer->getId(), $result['customerId']);
        $this->assertSame('John', $result['firstName']);
        $this->assertSame('Doe', $result['lastName']);
    }
}
