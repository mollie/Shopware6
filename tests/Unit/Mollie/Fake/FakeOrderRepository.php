<?php
declare(strict_types=1);


namespace Mollie\Unit\Mollie\Fake;

use Mollie\Api\Resources\OrderLineCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\Test\TestDefaults;

final class FakeOrderRepository
{
    private FakeCustomerRepository $customerRepository;
    public function __construct()
    {
        $this->customerRepository = new FakeCustomerRepository();
    }

    public function getDefaultOrder():OrderEntity
    {
        $customer = $this->customerRepository->getDefaultOrderCustomer();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $locale = new LocaleEntity();
        $locale->setCode('en-GB');
        $language = new LanguageEntity();
        $language->setLocale($locale);
        $order = new OrderEntity();
        $order->setOrderNumber('10000');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $order->setBillingAddress($this->getOrderAddress($customer));
        $order->setOrderCustomer($customer);
        $order->setDeliveries($this->getOrderDeliveries($customer));
        $order->setCurrency($currency);
        $order->setAmountTotal(100.00);
        $order->setTaxStatus(CartPrice::TAX_STATE_NET);
        $order->setLanguage($language);
        $order->setLineItems($this->getLineItems());
        if(method_exists($order,'getPrimaryOrderDeliveryId')){
            $order->getPrimaryOrderDeliveryId('fake-delivery-id');
        }
        return $order;
    }

    private function getLineItems():OrderLineItemCollection
    {
        $collection = new OrderLineItemCollection();

        $product = new ProductEntity();
        $product->setProductNumber('SW1000');
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId('fake-line-item-id');
        $orderLineItem->setPrice($this->getPrice(10.99,19));
        $orderLineItem->setLabel('Fake product');
        $orderLineItem->setProduct($product);
        $collection->add($orderLineItem);
        return $collection;

    }
    public function getOrderAddress(OrderCustomerEntity $customerEntity):OrderAddressEntity
    {
        $country = new CountryEntity();
        $country->setIso('DE');
        $orderAddress = new OrderAddressEntity();
        $orderAddress->setSalutation($customerEntity->getSalutation());
        $orderAddress->setFirstName($customerEntity->getFirstName());
        $orderAddress->setLastName($customerEntity->getLastName());
        $orderAddress->setStreet('Test Street');
        $orderAddress->setZipCode('12345');
        $orderAddress->setCity('Test City');
        $orderAddress->setCountry($country);
        return $orderAddress;
    }
    private function getPrice(float $unitPrice,float $taxRate,int $quantity = 1):CalculatedPrice
    {
        $totalPrice = $unitPrice * $quantity;

        $taxAmount = $unitPrice * ($taxRate / 100);

        $calculatedTax = new CalculatedTax($taxAmount, $taxRate, $unitPrice);


        return new CalculatedPrice($totalPrice, $unitPrice, new CalculatedTaxCollection([$calculatedTax]),  new TaxRuleCollection(), $quantity);

    }
    private function getOrderDeliveries(OrderCustomerEntity $customer): OrderDeliveryCollection
    {
        $collection = new OrderDeliveryCollection();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fake-shipping-method-id');
        $shippingMethod->setName('DHL');

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('fake-delivery-id');
        $delivery->setShippingOrderAddress($this->getOrderAddress($customer));
        $delivery->setShippingCosts($this->getPrice(4.99,19.0));
        $delivery->setShippingMethod($shippingMethod);

        $collection->add($delivery);

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('fake-free-delivery-id');
        $delivery->setShippingOrderAddress($this->getOrderAddress($customer));
        $delivery->setShippingCosts($this->getPrice(0.0,19.0));
        $delivery->setShippingMethod($shippingMethod);
        $collection->add($delivery);
        return $collection;
    }
}