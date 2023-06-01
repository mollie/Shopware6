<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\Cart\Validator\SubscriptionCartValidator;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionReminder\ReminderValidator;
use Kiener\MolliePayments\Components\Subscription\Services\Validator\MixedOrderValidator;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreateAction extends BaseAction
{
    private const INITIAL_STATUS = SubscriptionStatus::PENDING;



    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @throws Exception
     * @return string
     */
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string
    {
        if (!$this->isSubscriptionFeatureEnabled($order)) {
            return '';
        }

        # -------------------------------------------------------------------------------------

        if ($order->getLineItems() === null) {
            # empty carts are not allowed for subscriptions
            return '';
        }

        $mixedOrderValidator = new MixedOrderValidator();

        if ($mixedOrderValidator->isMixedCart($order)) {
            # Mixed orders are not allowed for subscriptions
            return '';
        }


        $item = $order->getLineItems()->first();

        if (!$item instanceof OrderLineItemEntity) {
            throw new Exception('No line item entity found for order ' . $order->getOrderNumber());
        }

        # ------------------------------------------------------------------------------------------------------------------------

        $attributes = new OrderLineItemEntityAttributes($item);

        if (!$attributes->isSubscriptionProduct()) {
            # this is no subscription product (regular checkout), so return an empty string.
            # return an empty string that will be saved as "reference".
            # so our order will not be a subscription
            return '';
        }

        if ($attributes->getSubscriptionInterval() <= 0) {
            throw new Exception('Invalid subscription interval unit');
        }

        if (empty($attributes->getSubscriptionIntervalUnit())) {
            throw new Exception('Invalid subscription interval unit');
        }

        # ------------------------------------------------------------------------------------------------------------------------

        $this->getLogger()->debug('Creating subscription entry for order: ' . $order->getOrderNumber());

        $subscription = $this->getSubscriptionBuilder()->buildSubscription($order);

        $this->getRepository()->insertSubscription($subscription, self::INITIAL_STATUS, $context->getContext());


        # fetch subscription again, to have correct data like createAt and more
        $subscription = $this->getRepository()->findById($subscription->getId(), $context->getContext());


        $this->getStatusHistory()->markCreated($subscription, self::INITIAL_STATUS, $context->getContext());

        return $subscription->getId();
    }
}
