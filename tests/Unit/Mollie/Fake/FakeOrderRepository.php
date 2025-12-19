<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use Mollie\Shopware\Component\Mollie\VoucherCategory;
use Mollie\Shopware\Component\Mollie\VoucherCategoryCollection;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Test\TestDefaults;

final class FakeOrderRepository
{
    public function getDefaultOrder($customer): OrderEntity
    {
        $order = new OrderEntity();
        $order->setOrderNumber('10000');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $order->setBillingAddress($this->getOrderAddress($customer));

        $order->setDeliveries($this->getOrderDeliveries($customer));
        $order->setAmountTotal(100.00);
        $order->setTaxStatus(CartPrice::TAX_STATE_NET);
        $order->setLineItems($this->getLineItems());
        if (method_exists($order, 'setPrimaryOrderDeliveryId')) {
            $order->setPrimaryOrderDeliveryId('fake-delivery-id');
        }

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setTechnicalName('open');
        $stateMachineState->setId('openFakeStateId');

        $order->setStateId($stateMachineState->getId());
        $order->setStateMachineState($stateMachineState);

        return $order;
    }

    public function getOrderAddress(CustomerEntity $customerEntity): OrderAddressEntity
    {
        $orderAddress = $this->getOrderAddressWithoutCountry($customerEntity);
        $country = new CountryEntity();
        $country->setIso('DE');
        $orderAddress->setCountry($country);

        return $orderAddress;
    }

    public function getOrderAddressWithoutCountry(CustomerEntity $customerEntity): OrderAddressEntity
    {
        $orderAddress = new OrderAddressEntity();
        if ($customerEntity->getSalutation() instanceof SalutationEntity) {
            $orderAddress->setSalutation($customerEntity->getSalutation());
        }
        $orderAddress->setFirstName($customerEntity->getFirstName());
        $orderAddress->setLastName($customerEntity->getLastName());
        $orderAddress->setStreet('Test Street');
        $orderAddress->setZipCode('12345');
        $orderAddress->setCity('Test City');

        return $orderAddress;
    }

    public function getOrderDeliveries(CustomerEntity $customer): OrderDeliveryCollection
    {
        $collection = new OrderDeliveryCollection();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fake-shipping-method-id');
        $shippingMethod->setName('DHL');

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('fake-delivery-id');
        $delivery->setShippingOrderAddress($this->getOrderAddress($customer));
        $delivery->setShippingCosts($this->getPrice(4.99, 19.0));
        $delivery->setShippingMethod($shippingMethod);

        $collection->add($delivery);

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('fake-free-delivery-id');
        $delivery->setShippingOrderAddress($this->getOrderAddress($customer));
        $delivery->setShippingCosts($this->getPrice(0.0, 19.0));
        $delivery->setShippingMethod($shippingMethod);
        $collection->add($delivery);

        return $collection;
    }

    public function getOrderDeliveryWithoutShippingCosts(): OrderDeliveryEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fake-shipping-method-id');
        $shippingMethod->setName('DHL');

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('fake-delivery-without-costs');
        $delivery->setShippingMethod($shippingMethod);
        $shippingCosts = new CalculatedPrice(4.99, 4.99, new CalculatedTaxCollection(), new TaxRuleCollection(), 1);
        $delivery->setShippingCosts($shippingCosts);

        return $delivery;
    }

    public function getOrderLineItemWithoutPrice(): OrderLineItemEntity
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId('fake-line-item-id');
        $orderLineItem->setLabel('Fake product');

        return $orderLineItem;
    }

    public function getLineItems(): OrderLineItemCollection
    {
        $collection = new OrderLineItemCollection();
        $orderLineItem = $this->createOrderLineItem('fake-line-item-id', 'SW1000', 'Fake product', 10.99);
        $collection->add($orderLineItem);

        return $collection;
    }

    public function getLineItemWithVoucherCategory(): OrderLineItemEntity
    {
        return $this->createOrderLineItem('fake-line-item-voucher-id', 'SW1001', 'Voucher product', 25.00, [1, 2]);
    }

    public function getLineItemWithSingleVoucherCategory(): OrderLineItemEntity
    {
        return $this->createOrderLineItem(
            'fake-line-item-single-voucher-id', 'SW1002', 'Single voucher product', 30.00, [1]);
    }

    public function getLineItemWithMixedVoucherCategories(): OrderLineItemEntity
    {
        return $this->createOrderLineItem('fake-line-item-mixed-voucher-id', 'SW1003', 'Mixed voucher product', 35.00, [1, 99, 2]);
    }

    private function createOrderLineItem(string $id, string $productNumber, string $label, float $price, $voucherCategories = null): OrderLineItemEntity
    {
        $product = new ProductEntity();
        $product->setProductNumber($productNumber);
        $extension = new Product();
        if ($voucherCategories !== null) {
            $voucherCategoriesCollection = new VoucherCategoryCollection();
            foreach ($voucherCategories as $voucherCategory) {
                $voucherCategory = VoucherCategory::tryFromNumber($voucherCategory);
                if ($voucherCategory === null) {
                    continue;
                }
                $voucherCategoriesCollection->add($voucherCategory);
            }
            $extension->setVoucherCategories($voucherCategoriesCollection);
            $product->addExtension(Mollie::EXTENSION,$extension);
        }

        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId($id);
        $orderLineItem->setPrice($this->getPrice($price, 19));
        $orderLineItem->setLabel($label);
        $orderLineItem->setProduct($product);

        return $orderLineItem;
    }

    private function getPrice(float $unitPrice, float $taxRate, int $quantity = 1): CalculatedPrice
    {
        $totalPrice = $unitPrice * $quantity;

        $taxAmount = $unitPrice * ($taxRate / 100);

        $calculatedTax = new CalculatedTax($taxAmount, $taxRate, $unitPrice);

        return new CalculatedPrice($totalPrice, $unitPrice, new CalculatedTaxCollection([$calculatedTax]), new TaxRuleCollection(), $quantity);
    }
}
