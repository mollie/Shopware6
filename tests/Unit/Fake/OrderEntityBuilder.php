<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
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
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Test\TestDefaults;

final class OrderEntityBuilder
{
    public function getDefaultOrder($customer): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('fakeShopwareOrderId');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $order->setBillingAddress($this->getOrderAddress($customer));

        $order->setDeliveries($this->getOrderDeliveries($customer));
        $order->setAmountTotal(100.00);
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
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
        $country->setId('country-id');
        $country->setIso('DE');
        $orderAddress->setCountry($country);
        $orderAddress->setCountryId('country-id');

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

    public function getDeliveryDiscountPromotionLineItem(string $label): OrderLineItemEntity
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId('fake-shipping-promotion-line-item-id');
        $orderLineItem->setType('promotion');
        $orderLineItem->setLabel($label);
        $orderLineItem->setQuantity(1);
        $orderLineItem->setPrice($this->getPrice(0.0, 0.0));
        $orderLineItem->setPayload(['discountScope' => 'delivery']);

        return $orderLineItem;
    }

    public function getShippingDiscountDelivery(CustomerEntity $customer): OrderDeliveryEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fake-shipping-method-id');
        $shippingMethod->setName('DHL');

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('fake-shipping-discount-delivery-id');
        $delivery->setShippingOrderAddress($this->getOrderAddress($customer));
        $delivery->setShippingCosts($this->getPrice(-4.99, 19.0));
        $delivery->setShippingMethod($shippingMethod);

        return $delivery;
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

    public function getOrderDeliveryWithEmptyShippingMethodName(CustomerEntity $customer): OrderDeliveryEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fake-shipping-method-id');
        $shippingMethod->setName('   ');

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('fake-delivery-empty-name');
        $delivery->setShippingOrderAddress($this->getOrderAddress($customer));
        $delivery->setShippingCosts($this->getPrice(4.99, 19.0));
        $delivery->setShippingMethod($shippingMethod);

        return $delivery;
    }

    public function getOrderDeliveryWithTranslatedShippingMethodName(CustomerEntity $customer): OrderDeliveryEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fake-shipping-method-id');
        $shippingMethod->setTranslated(['name' => 'DHL Translated']);

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('fake-delivery-translated-name');
        $delivery->setShippingOrderAddress($this->getOrderAddress($customer));
        $delivery->setShippingCosts($this->getPrice(4.99, 19.0));
        $delivery->setShippingMethod($shippingMethod);

        return $delivery;
    }

    public function getOrderLineItemWithoutPrice(): OrderLineItemEntity
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId('fake-line-item-id');
        $orderLineItem->setLabel('Fake product');

        return $orderLineItem;
    }

    public function createOrderLineItemWithType(string $id, string $type, float $unitPrice = 0.0): OrderLineItemEntity
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId($id);
        $orderLineItem->setType($type);
        $orderLineItem->setLabel('Fake line item');
        $orderLineItem->setQuantity(1);
        $orderLineItem->setPrice($this->getPrice($unitPrice, 19.0));
        $orderLineItem->setPayload([]);

        return $orderLineItem;
    }

    public function getLineItems(): OrderLineItemCollection
    {
        $collection = new OrderLineItemCollection();
        $orderLineItem = $this->createOrderLineItem('fake-line-item-id', 'SW1000', 'Fake product', 10.99);
        $collection->add($orderLineItem);

        return $collection;
    }

    public function getSubscriptionLineItems(): OrderLineItemCollection
    {
        $subscriptionExtension = new Product();
        $subscriptionExtension->setIsSubscription(true);

        $orderLineItem = $this->createOrderLineItem('fake-subscription-line-item-id', 'SW2000', 'Fake subscription product', 10.99);
        $orderLineItem->addExtension(Mollie::EXTENSION, $subscriptionExtension);

        $collection = new OrderLineItemCollection();
        $collection->add($orderLineItem);

        return $collection;
    }

    /**
     * Builds an order line item that carries everything the shipment flow reads: quantity, unit price,
     * a product (for product-number resolution) and the Mollie custom fields.
     *
     * @param array<string, mixed> $mollieCustomFields
     */
    public function createShippableLineItem(string $id, string $productNumber, int $quantity, float $unitPrice, array $mollieCustomFields = []): OrderLineItemEntity
    {
        $product = new ProductEntity();
        $product->setProductNumber($productNumber);
        $product->setName('Product ' . $productNumber);

        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId($id);
        $orderLineItem->setLabel('Product ' . $productNumber);
        $orderLineItem->setQuantity($quantity);
        $orderLineItem->setUnitPrice($unitPrice);
        $orderLineItem->setProduct($product);
        $orderLineItem->setCustomFields($mollieCustomFields === [] ? [] : [Mollie::EXTENSION => $mollieCustomFields]);

        return $orderLineItem;
    }

    /**
     * Builds a minimal order that was paid with Mollie (transaction carries the Mollie Payment
     * extension), suitable for the shipment flow.
     */
    public function getOrderWithMolliePayment(OrderLineItemCollection $lineItems, ?Payment $payment = null): OrderEntity
    {
        $payment ??= new Payment('tr_fake_payment');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('fake-transaction-id');
        $transaction->addExtension(Mollie::EXTENSION, $payment);

        $currency = new CurrencyEntity();
        $currency->setId('fake-currency-id');
        $currency->setIsoCode('EUR');

        $order = new OrderEntity();
        $order->setId('fakeshopwareorderid');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $order->setCurrency($currency);
        $order->setLineItems($lineItems);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
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

    /**
     * Builds a discount line item that spans multiple tax rates, as produced by a
     * percentage voucher applied to a cart with products of different tax rates.
     * The CalculatedTax entries use the net base (Shopware net tax state), mirroring
     * the production scenario where the API rejected the blended vatAmount.
     */
    public function getDiscountLineItemWithMultipleTaxesNet(): OrderLineItemEntity
    {
        $taxes = new CalculatedTaxCollection([
            new CalculatedTax(-0.651, 7.0, -9.30),
            new CalculatedTax(-2.3845, 19.0, -12.55),
        ]);

        $netTotal = -21.85;
        $price = new CalculatedPrice($netTotal, $netTotal, $taxes, new TaxRuleCollection(), 1);

        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId('fake-discount-line-item-id');
        $orderLineItem->setType('promotion');
        $orderLineItem->setLabel('50 percent discount');
        $orderLineItem->setQuantity(1);
        $orderLineItem->setPrice($price);

        return $orderLineItem;
    }

    public function getLineItemWithWholeNumberRoundedTax(): OrderLineItemEntity
    {
        // currencies with zero item-rounding decimals (e.g. PLN) store whole-number taxes:
        // 678.00 at 23% is stored as 127.00 instead of 126.78
        $taxes = new CalculatedTaxCollection([
            new CalculatedTax(127.0, 23.0, 678.0),
        ]);

        $price = new CalculatedPrice(678.0, 678.0, $taxes, new TaxRuleCollection(), 1);

        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId('fake-whole-number-tax-line-item-id');
        $orderLineItem->setType('product');
        $orderLineItem->setLabel('Whole number tax product');
        $orderLineItem->setQuantity(1);
        $orderLineItem->setPrice($price);

        return $orderLineItem;
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

        $taxAmount = $totalPrice * ($taxRate / 100);

        $calculatedTax = new CalculatedTax($taxAmount, $taxRate, $totalPrice);

        return new CalculatedPrice($totalPrice, $unitPrice, new CalculatedTaxCollection([$calculatedTax]), new TaxRuleCollection(), $quantity);
    }
}
