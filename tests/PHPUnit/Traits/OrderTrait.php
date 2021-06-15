<?php declare(strict_types=1);


namespace MolliePayments\Tests\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

trait OrderTrait
{
    public function getCustomerAddressEntity(
        string $firstName,
        string $lastName,
        string $street,
        string $zipCode,
        string $city,
        ?string $salutationName,
        ?string $countryISO,
        ?string $additional): CustomerAddressEntity
    {
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());

        if (!empty($salutationName)) {
            $salutation = new SalutationEntity();
            $salutation->setId(Uuid::randomHex());
            $salutation->setDisplayName($salutationName);
            $customerAddress->setSalutation($salutation);
        }

        $customerAddress->setFirstName($firstName);
        $customerAddress->setLastName($lastName);
        $customerAddress->setStreet($street);
        if (!empty($additional)) {
            $customerAddress->setAdditionalAddressLine1($additional);
        }

        $customerAddress->setZipcode($zipCode);
        $customerAddress->setCity($city);

        if (!empty($countryISO)) {
            $country = new CountryEntity();
            $country->setId(Uuid::randomHex());
            $country->setIso($countryISO);
            $customerAddress->setCountry($country);
        }

        return $customerAddress;
    }

//    public function getDummyAddress(): OrderAddressEntity
//    {
//        $firstName = 'firstName';
//        $lastName = 'lastName';
//        $street = 'street';
//        $zipCode = 'zipCode';
//        $city = 'city';
//        $salutation = 'salutation';
//        $iso = 'DE';
//        $additional = 'additional';
//
//        $address=new OrderAddressEntity();
//
//        return $this->getCustomerAddressEntity(
//            $firstName,
//            $lastName,
//            $street,
//            $zipCode,
//            $city,
//            $salutation,
//            $iso,
//            $additional
//        );
//    }

    public function getOrder(string $isoCode, OrderLineItemCollection $lineItems, string $taxStatus = CartPrice::TAX_STATE_GROSS): OrderEntity
    {
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($isoCode);

        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setLineItems($lineItems);
        $order->setCurrency($currency);
        $order->setTaxStatus($taxStatus);

        return $order;
    }

    public function getOrderLineItem(
        string $lineItemId,
        string $productNumber,
        string $label,
        int $unit,
        float $unitPrice,
        float $taxRate,
        float $taxAmount,
        string $lineItemType = LineItem::PRODUCT_LINE_ITEM_TYPE,
        string $seoUrl = '',
        string $imageUrl = ''
    ): OrderLineItemEntity
    {
        $productId = Uuid::randomHex();
        $totalPrice = $unit * $unitPrice;
        $calculatedTax = new CalculatedTax($taxAmount, $taxRate, $totalPrice);
        $taxes = new CalculatedTaxCollection([$calculatedTax]);
        $rules = new TaxRuleCollection([]);
        $price = new CalculatedPrice($unitPrice, $totalPrice, $taxes, $rules, $unit);

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($lineItemId);
        $lineItem->setPrice($price);
        $lineItem->setTotalPrice($totalPrice);
        $lineItem->setLabel($label);
        $lineItem->setQuantity($unit);
        $lineItem->setType($lineItemType);

        $product = new ProductEntity();
        $product->setId($productId);
        $product->setProductNumber($productNumber);
        if (!empty($seoUrl)) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setId(Uuid::randomHex());
            $seoUrlEntity->setUrl($seoUrl);
            $seoUrls = new SeoUrlCollection([$seoUrlEntity]);
            $product->setSeoUrls($seoUrls);
        }
        if (!empty($imageUrl)) {
            $mediaEntity = new MediaEntity();
            $mediaEntity->setId(Uuid::randomHex());
            $mediaEntity->setUrl($imageUrl);
            $productMediaEntity = new ProductMediaEntity();
            $productMediaEntity->setId(Uuid::randomHex());
            $productMediaEntity->setMedia($mediaEntity);
            $medias = new ProductMediaCollection([$productMediaEntity]);
            $product->setMedia($medias);
        }

        $lineItem->setProduct($product);

        return $lineItem;
    }

    public function getExpectedTestAddress(CustomerAddressEntity $address, string $email): array
    {
        return [
            'title' => $address->getSalutation()->getDisplayName(),
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $email,
            'streetAndNumber' => $address->getStreet(),
            'streetAdditional' => $address->getAdditionalAddressLine1(),
            'postalCode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry()->getIso(),
        ];
    }

    public function getDummyAddress(): CustomerAddressEntity
    {
        $salutation = 'Mr';
        $firstName = 'foo';
        $lastName = 'bar';
        $street = 'foostreet';
        $additional = 'additional';
        $zip = '12345';
        $city = 'city';
        $country = 'DE';

        return $this->getCustomerAddressEntity($firstName, $lastName, $street, $zip, $city, $salutation, $country, $additional);
    }
}
