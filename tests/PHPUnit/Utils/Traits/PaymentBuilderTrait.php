<?php declare(strict_types=1);

namespace MolliePayments\Tests\Utils\Traits;

use Kiener\MolliePayments\Hydrator\MollieLineItemHydrator;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieShippingLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

trait PaymentBuilderTrait
{
    /**
     * @param CustomerAddressEntity $address
     * @param string $email
     * @return array<string,mixed>
     */
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

    private function getDummyCustomer(CustomerAddressEntity $billing, CustomerAddressEntity $shipping, string $email): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setDefaultBillingAddress($billing);
        $customer->setDefaultShippingAddress($shipping);
        $customer->setEmail($email);

        return $customer;
    }

    /**
     * @param string $taxStatus
     * @param OrderLineItemCollection|null $lineItems
     * @param CurrencyEntity|null $currency
     * @return array<string,mixed>
     */
    public function getExpectedLineItems(string $taxStatus, ?OrderLineItemCollection $lineItems = null, CurrencyEntity $currency): array
    {
        $expectedLineItems = [];

        $mollieLineItemBuilder = new MollieLineItemBuilder(new IsOrderLineItemValid(), new PriceCalculator(), new LineItemDataExtractor());

        /** @var OrderLineItemEntity $item */
        foreach ($lineItems as $item) {
            $expectedLineItems = $mollieLineItemBuilder->buildLineItems($taxStatus, $lineItems, false);
        }

        $hydrator = new MollieLineItemHydrator(new MollieOrderPriceBuilder());

        return $hydrator->hydrate($expectedLineItems, $currency->getIsoCode());
    }

    public function getExpectedDeliveries(string $taxStatus, ?OrderDeliveryCollection $deliveries = null, CurrencyEntity $currency): array
    {
        $mollieShippingLineItemBuilder = new MollieShippingLineItemBuilder(new PriceCalculator());

        $hydrator = new MollieLineItemHydrator(new MollieOrderPriceBuilder());

        return $hydrator->hydrate($mollieShippingLineItemBuilder->buildShippingLineItems($taxStatus, $deliveries), $currency->getIsoCode());
    }

    public function getDummyLineItems(): OrderLineItemCollection
    {
        $productNumber = 'foo';
        $labelName = 'bar';
        $quantity = 1;
        $taxRate = 50.0;
        $taxAmount = 0.0;
        $unitPrice = 15.0;
        $lineItemId = Uuid::randomHex();
        $seoUrl = 'http://seoUrl';
        $imageUrl = 'http://imageUrl';
        $positionOne = 1;

        $productNumberTwo = 'baz';
        $labelNameTwo = 'foobar';
        $quantityTwo = 1;
        $taxRateTwo = 30.0;
        $taxAmountTwo = 0.0;
        $unitPriceTwo = 12.0;
        $lineItemIdTwo = Uuid::randomHex();
        $seoUrlTwo = 'http://seoUrl2';
        $imageUrlTwo = 'http://imageUrl2';
        $positionTwo = 2;

        $lineItemOne = $this->getOrderLineItem(
            $lineItemId,
            $productNumber,
            $labelName,
            $quantity,
            $unitPrice,
            $taxRate,
            $taxAmount,
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $seoUrl,
            $imageUrl,
            $positionOne
        );

        $lineItemTwo = $this->getOrderLineItem(
            $lineItemIdTwo,
            $productNumberTwo,
            $labelNameTwo,
            $quantityTwo,
            $unitPriceTwo,
            $taxRateTwo,
            $taxAmountTwo,
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $seoUrlTwo,
            $imageUrlTwo,
            $positionTwo
        );

        return new OrderLineItemCollection([$lineItemOne, $lineItemTwo]);
    }

    public function getOrderEntity(
        float                   $amountTotal,
        string                  $taxStatus,
        CurrencyEntity          $currency,
        OrderLineItemCollection $lineItems,
        string                  $orderNumber
    ): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $order->setAmountTotal($amountTotal);
        $order->setTaxStatus($taxStatus);
        $order->setCurrency($currency);
        $order->setLineItems($lineItems);
        $order->setOrderNumber($orderNumber);

        return $order;
    }
}
