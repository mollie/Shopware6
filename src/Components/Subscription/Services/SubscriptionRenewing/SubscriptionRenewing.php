<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\SubscriptionRenewing;


use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\OrderService;
use Mollie\Api\Resources\Payment;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubscriptionRenewing
{

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRanges;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoOrderAddress;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var OrderCloneService
     */
    private $orderCloneService;


    /**
     * @param NumberRangeValueGeneratorInterface $numberRanges
     * @param EntityRepositoryInterface $repoOrderAddress
     * @param OrderService $orderService
     * @param OrderCloneService $orderCloneService
     */
    public function __construct(NumberRangeValueGeneratorInterface $numberRanges, EntityRepositoryInterface $repoOrderAddress, OrderService $orderService, OrderCloneService $orderCloneService)
    {
        $this->numberRanges = $numberRanges;
        $this->repoOrderAddress = $repoOrderAddress;
        $this->orderService = $orderService;
        $this->orderCloneService = $orderCloneService;
    }


    /**
     * @param SubscriptionEntity $subscription
     * @param Payment $molliePayment
     * @param SalesChannelContext $context
     * @return OrderEntity
     * @throws \Exception
     */
    public function renewSubscription(SubscriptionEntity $subscription, Payment $molliePayment, SalesChannelContext $context): OrderEntity
    {
        $order = $this->orderService->getOrder($subscription->getOrderId(), $context->getContext());

        if (!$order instanceof OrderEntity) {
            throw new EntityNotFoundException('order', $subscription->getOrderId());
        }

        # get the next order number
        $newOrderNumber = $this->numberRanges->getValue('order', $context->getContext(), $subscription->getSalesChannelId());


        # if we have a separate shipping address
        # make sure that our cloned order also contains 2 addresses (1 for shipping)
        $needsSeparateShippingAddress = ($subscription->getShippingAddress() instanceof SubscriptionAddressEntity);

        # now let's clone our previous order and create a new one from it
        $orderId = $this->orderCloneService->createNewOrder($order, $newOrderNumber, $needsSeparateShippingAddress, $context->getContext());

        $order = $this->orderService->getOrder($orderId, $context->getContext());

        if (!$order instanceof OrderEntity) {
            throw new \Exception('Cannot renew subscription. Order with ID ' . $orderId . ' not found for subscription: ' . $subscription->getMollieId());
        }

        if (!$order->getTransactions() instanceof OrderTransactionCollection) {
            throw new \Exception('Order ' . $order->getOrderNumber() . ' does not have a list of order transactions');
        }

        $lastTransaction = $order->getTransactions()->last();

        if (!$lastTransaction instanceof OrderTransactionEntity) {
            throw new \Exception('Order ' . $order->getOrderNumber() . ' does not have a last order transaction');
        }


        $billing = $subscription->getBillingAddress();

        # now update the billing and shipping address
        if ($billing instanceof SubscriptionAddressEntity) {
            $this->repoOrderAddress->update([
                [
                    'id' => $order->getBillingAddressId(),
                    'salutationId' => $billing->getSalutationId(),
                    'title' => $billing->getTitle(),
                    'firstName' => $billing->getFirstName(),
                    'lastName' => $billing->getLastName(),
                    'company' => $billing->getCompany(),
                    'department' => $billing->getDepartment(),
                    'additionalAddressLine1' => $billing->getAdditionalAddressLine1(),
                    'additionalAddressLine2' => $billing->getAdditionalAddressLine2(),
                    'phoneNumber' => $billing->getPhoneNumber(),
                    'street' => $billing->getStreet(),
                    'zipcode' => $billing->getZipcode(),
                    'city' => $billing->getCity(),
                    'countryId' => $billing->getCountryId(),
                    'countryStateId' => $billing->getCountryStateId(),
                ]
            ],
                $context->getContext()
            );
        }

        $shipping = $subscription->getShippingAddress();

        if ($shipping instanceof SubscriptionAddressEntity && $order->getDeliveries() instanceof OrderDeliveryCollection) {
            foreach ($order->getDeliveries() as $delivery) {
                $this->repoOrderAddress->update([
                    [
                        'id' => $delivery->getShippingOrderAddressId(),
                        'salutationId' => $shipping->getSalutationId(),
                        'title' => $shipping->getTitle(),
                        'firstName' => $shipping->getFirstName(),
                        'lastName' => $shipping->getLastName(),
                        'company' => $shipping->getCompany(),
                        'department' => $shipping->getDepartment(),
                        'additionalAddressLine1' => $shipping->getAdditionalAddressLine1(),
                        'additionalAddressLine2' => $shipping->getAdditionalAddressLine2(),
                        'phoneNumber' => $shipping->getPhoneNumber(),
                        'street' => $shipping->getStreet(),
                        'zipcode' => $shipping->getZipcode(),
                        'city' => $shipping->getCity(),
                        'countryId' => $shipping->getCountryId(),
                        'countryStateId' => $shipping->getCountryStateId(),
                    ]
                ],
                    $context->getContext()
                );
            }
        }

        # also make sure to update our metadata
        # that is stored in the custom fields of the
        # Shopware order and its transactions
        $this->orderService->updateMollieData(
            $order,
            $lastTransaction->getId(),
            '',
            $subscription->getId(),
            $subscription->getMollieId(),
            $molliePayment,
            $context
        );


        return $order;
    }

}
