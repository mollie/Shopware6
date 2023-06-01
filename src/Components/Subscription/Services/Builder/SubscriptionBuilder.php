<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\Builder;

use Exception;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\Services\Interval\IntervalCalculator;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

class SubscriptionBuilder
{
    /**
     * @var IntervalCalculator
     */
    private $intervalCalculator;


    /**
     */
    public function __construct()
    {
        $this->intervalCalculator = new IntervalCalculator();
    }


    /**
     * @param OrderEntity $order
     * @throws Exception
     * @return SubscriptionEntity
     */
    public function buildSubscription(OrderEntity $order): SubscriptionEntity
    {
        if (!$order->getLineItems() instanceof OrderLineItemCollection) {
            throw new Exception('Order does not have line items');
        }

        $item = $order->getLineItems()->first();

        if (!$item instanceof OrderLineItemEntity) {
            throw new Exception('Order does not have a valid line item');
        }

        return $this->buildItemSubscription($item, $order);
    }

    /**
     * @param OrderLineItemEntity $lineItem
     * @param OrderEntity $order
     * @throws Exception
     * @return SubscriptionEntity
     */
    private function buildItemSubscription(OrderLineItemEntity $lineItem, OrderEntity $order): SubscriptionEntity
    {
        if (!$order->getCurrency() instanceof CurrencyEntity) {
            throw new Exception('Order does not have a currency');
        }

        if (!$order->getOrderCustomer() instanceof OrderCustomerEntity) {
            throw new Exception('Order does not have an order customer entity');
        }

        $attributes = new OrderLineItemEntityAttributes($lineItem);

        $interval = $attributes->getSubscriptionInterval();
        $intervalUnit = $attributes->getSubscriptionIntervalUnit();

        $times = $attributes->getSubscriptionRepetitionCount();

        $description = $lineItem->getQuantity() . 'x ' . $lineItem->getLabel() . ' (Order #' . $order->getOrderNumber() . ', ' . $lineItem->getTotalPrice() . ' ' . $order->getCurrency()->getIsoCode() . ')';

        # -----------------------------------------------------------------------------------------

        $subscriptionEntity = new SubscriptionEntity();
        $subscriptionEntity->setId(Uuid::randomHex());

        $subscriptionEntity->setDescription($description);

        # ATTENTION
        # the amount needs to be the total amount of our order
        # and not the price amount. because it would have shipping as well
        # as promotions.  because we only offer subscriptions as a 1-item order without mixed carts,
        # this is the perfect way to still have shopware doing every calculation.
        $subscriptionEntity->setAmount($order->getAmountTotal());
        $subscriptionEntity->setCurrency($order->getCurrency()->getIsoCode());

        $subscriptionEntity->setQuantity($lineItem->getQuantity());

        $subscriptionEntity->setCustomerId((string)$order->getOrderCustomer()->getCustomerId());
        $subscriptionEntity->setProductId((string)$lineItem->getProductId());
        $subscriptionEntity->setOrderId($order->getId());
        $subscriptionEntity->setSalesChannelId($order->getSalesChannelId());


        # calculate our first start date.
        # this is our current date (now) + 1x the planned interval.
        # we already charge now, so we start the recurrency in 1 interval.
        $firstStartDate = $this->intervalCalculator->getNextIntervalDate(
            $order->getOrderDateTime(),
            $interval,
            $intervalUnit
        );

        $subscriptionEntity->setMetadata(
            new SubscriptionMetadata(
                $firstStartDate,
                $interval,
                $intervalUnit,
                $times,
                ''
            )
        );

        $orderAddress = $order->getBillingAddress();

        if ($orderAddress instanceof OrderAddressEntity) {
            $address = new SubscriptionAddressEntity();
            $address->setId(Uuid::randomHex());

            $address->setSalutationId($orderAddress->getSalutationId());
            $address->setFirstName($orderAddress->getFirstName());
            $address->setLastName($orderAddress->getLastName());
            $address->setCompany($orderAddress->getCompany());
            $address->setDepartment($orderAddress->getDepartment());
            $address->setVatId($orderAddress->getVatId());
            $address->setStreet($orderAddress->getStreet());
            $address->setZipcode($orderAddress->getZipcode());
            $address->setCity($orderAddress->getCity());
            $address->setCountryId($orderAddress->getCountryId());
            $address->setCountryStateId($orderAddress->getCountryStateId());
            $address->setPhoneNumber($orderAddress->getPhoneNumber());
            $address->setAdditionalAddressLine1($orderAddress->getAdditionalAddressLine1());
            $address->setAdditionalAddressLine2($orderAddress->getAdditionalAddressLine2());

            $subscriptionEntity->setBillingAddressId($address->getId());
            $subscriptionEntity->setBillingAddress($address);
        }


        if ($order->getDeliveries() instanceof OrderDeliveryCollection) {
            foreach ($order->getDeliveries() as $delivery) {
                $shippingAddress = $delivery->getShippingOrderAddress();

                # if we have a different shipping address
                # then lets create it for our subscription
                if ($shippingAddress instanceof OrderAddressEntity && $shippingAddress->getId() !== $order->getBillingAddressId()) {
                    $address = new SubscriptionAddressEntity();
                    $address->setId(Uuid::randomHex());

                    $address->setSalutationId($shippingAddress->getSalutationId());
                    $address->setFirstName($shippingAddress->getFirstName());
                    $address->setLastName($shippingAddress->getLastName());
                    $address->setCompany($shippingAddress->getCompany());
                    $address->setDepartment($shippingAddress->getDepartment());
                    $address->setVatId($shippingAddress->getVatId());
                    $address->setStreet($shippingAddress->getStreet());
                    $address->setZipcode($shippingAddress->getZipcode());
                    $address->setCity($shippingAddress->getCity());
                    $address->setCountryId($shippingAddress->getCountryId());
                    $address->setCountryStateId($shippingAddress->getCountryStateId());
                    $address->setPhoneNumber($shippingAddress->getPhoneNumber());
                    $address->setAdditionalAddressLine1($shippingAddress->getAdditionalAddressLine1());
                    $address->setAdditionalAddressLine2($shippingAddress->getAdditionalAddressLine2());

                    $subscriptionEntity->setShippingAddressId($address->getId());
                    $subscriptionEntity->setShippingAddress($address);
                }
            }
        }

        return $subscriptionEntity;
    }
}
