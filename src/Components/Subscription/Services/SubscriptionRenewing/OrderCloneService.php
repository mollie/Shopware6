<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\SubscriptionRenewing;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Repository\Order\OrderRepositoryInterface;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderCloneService
{
    /**
     * @var OrderRepositoryInterface
     */
    private $repoOrders;

    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var Processor
     */
    private $processor;


    /**
     * @param OrderRepositoryInterface $repoOrders
     * @param OrderConverter $orderConverter
     * @param Processor $processor
     */
    public function __construct(OrderRepositoryInterface $repoOrders, OrderConverter $orderConverter, Processor $processor)
    {
        $this->repoOrders = $repoOrders;
        $this->orderConverter = $orderConverter;
        $this->processor = $processor;
    }


    /**
     * @param OrderEntity $existingOrder
     * @param string $newOrderNumber
     * @param bool $needsSeparateShippingAddress
     * @param Context $context
     * @throws \Exception
     * @return string
     */
    public function createNewOrder(OrderEntity $existingOrder, string $newOrderNumber, bool $needsSeparateShippingAddress, Context $context): string
    {
        if (!$existingOrder->getAddresses() instanceof OrderAddressCollection) {
            throw new \Exception('Order does not have an address collection');
        }

        if (!$existingOrder->getOrderCustomer() instanceof OrderCustomerEntity) {
            throw new \Exception('Order does not have an order customer assigned');
        }

        $newOrderId = Uuid::randomHex();


        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($existingOrder, $context);

        # we start by converting our existing order
        # into a cart. this one will be adjusted and later on converted into a new order
        $cart = $this->orderConverter->convertToCart($existingOrder, $context);


        $behavior = new CartBehavior($salesChannelContext->getPermissions());
        $cart = $this->processor->process($cart, $salesChannelContext, $behavior);


        $conversionContext = new OrderConversionContext();
        $conversionContext->setIncludeCustomer(true);
        $conversionContext->setIncludeBillingAddress(true);
        $conversionContext->setIncludeDeliveries(true);
        $conversionContext->setIncludeTransactions(true);
        $conversionContext->setIncludeOrderDate(false);


        $orderData = $this->orderConverter->convertToOrder($cart, $salesChannelContext, $conversionContext);


        # if we only have 1 billing address, but we need a separate
        # shipping address, then we need to duplicate the existing one to
        # have a separate shipping address
        $duplicateShippingAddress = (count($existingOrder->getAddresses()) <= 1) && $needsSeparateShippingAddress;

        # -----------------------------------------------------------------
        # adjust the data so that it has new IDs and will be inserted again

        $orderData['id'] = $newOrderId;
        $orderData['orderNumber'] = $newOrderNumber;
        $orderData['orderDateTime'] = new \DateTime();

        $orderData['orderCustomer'] = $this->getOrderCustomer($existingOrder->getOrderCustomer());
        $orderData['addresses'] = $this->getOrderAddresses($existingOrder->getAddresses(), $duplicateShippingAddress);

        # only set our first transaction.
        # we don't need the history, but without this, we don't have any transaction at all
        $orderData['transactions'] = [
            $orderData['transactions'][0]
        ];

        # we need a lookup and mapping of old address IDs and new ones
        # our new order has new IDs. the order structure has some
        # references to address which already exist in the exiting order.
        # we need to create those same references with our new IDs.
        # so we just store the [oldID] = $newID
        $mappingsAddressIDs = [];


        foreach ($orderData['addresses'] as $index => $address) {
            $oldAddressId = $orderData['addresses'][$index]['id'];
            $newAddressId = Uuid::randomHex();

            # add our mapping for this address
            $mappingsAddressIDs[$oldAddressId] = $newAddressId;

            $orderData['addresses'][$index]['id'] = $newAddressId;
        }


        # reference our new billing address id
        # for our new order
        $oldBillingAddressID = $existingOrder->getBillingAddressId();
        $orderData['billingAddressId'] = $mappingsAddressIDs[$oldBillingAddressID];


        foreach ($orderData['lineItems'] as $index => $lineitem) {
            $orderData['lineItems'][$index]['id'] = Uuid::randomHex();
        }

        foreach ($orderData['deliveries'] as $index => $delivery) {
            $oldDeliveryId = $orderData['deliveries'][$index]['id'];
            $newDeliveryId = Uuid::randomHex();

            $orderData['deliveries'][$index]['id'] = $newDeliveryId;


            if ($existingOrder->getDeliveries() instanceof OrderDeliveryCollection) {
                /** @var OrderDeliveryEntity $orderDelivery */
                $orderDelivery = $existingOrder->getDeliveries()->get($oldDeliveryId);

                $orderData['deliveries'][$index]['shippingOrderAddressId'] = $mappingsAddressIDs[$orderDelivery->getShippingOrderAddressId()];

                # if we have duplicated our billing address as shipping address
                # then we use the second ID as shipping in our duplicated address
                if ($duplicateShippingAddress) {
                    $orderData['deliveries'][$index]['shippingOrderAddressId'] = $orderData['addresses'][1]['id'];
                }
            }
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($orderData): void {
            $this->repoOrders->create([$orderData], $context);
        });

        return $newOrderId;
    }

    /**
     * @param OrderCustomerEntity $orderCustomer
     * @return array<mixed>
     */
    private function getOrderCustomer(OrderCustomerEntity $orderCustomer): array
    {
        return [
            'customerId' => $orderCustomer->getCustomerId(),
            'email' => $orderCustomer->getEmail(),
            'salutationId' => $orderCustomer->getSalutationId(),
            'firstName' => $orderCustomer->getFirstName(),
            'lastName' => $orderCustomer->getLastName(),
        ];
    }

    /**
     * @param OrderAddressCollection $addresses
     * @param bool $duplicateAddress
     * @return array<mixed>
     */
    private function getOrderAddresses(OrderAddressCollection $addresses, bool $duplicateAddress): array
    {
        $addressData = [];

        foreach ($addresses as $address) {
            $data = [
                'id' => $address->getId(),
                'salutationId' => $address->getSalutationId(),
                'firstName' => $address->getFirstName(),
                'lastName' => $address->getLastName(),
                'street' => $address->getStreet(),
                'zipcode' => $address->getZipcode(),
                'city' => $address->getCity(),
                'company' => $address->getCompany(),
                'department' => $address->getDepartment(),
                'title' => $address->getTitle(),
                'vatId' => $address->getVatId(),
                'phoneNumber' => $address->getPhoneNumber(),
                'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
                'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
                'countryId' => $address->getCountryId(),
                'countryStateId' => $address->getCountryStateId(),
            ];

            $addressData[] = $data;

            if ($duplicateAddress) {
                $data['id'] = Uuid::randomHex();
                $addressData[] = $data;
            }
        }

        return $addressData;
    }
}
