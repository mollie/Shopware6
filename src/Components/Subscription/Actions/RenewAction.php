<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\MollieStatus;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionSkippedException;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionRenewing\SubscriptionRenewing;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class RenewAction extends BaseAction
{
    /**
     * @var SubscriptionRenewing
     */
    private $renewingService;

    /**
     * @var OrderStatusConverter
     */
    private $statusConverter;


    /**
     * @param SettingsService $pluginSettings
     * @param SubscriptionRepository $repoSubscriptions
     * @param SubscriptionBuilder $subscriptionBuilder
     * @param MollieDataBuilder $mollieRequestBuilder
     * @param CustomerService $customers
     * @param MollieGatewayInterface $gwMollie
     * @param CancellationValidator $cancellationValidator
     * @param FlowBuilderFactory $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param SubscriptionHistoryHandler $subscriptionHistory
     * @param LoggerInterface $logger
     * @param SubscriptionRenewing $renewingService
     * @param OrderStatusConverter $statusConverter
     * @throws Exception
     */
    public function __construct(SettingsService $pluginSettings, SubscriptionRepository $repoSubscriptions, SubscriptionBuilder $subscriptionBuilder, MollieDataBuilder $mollieRequestBuilder, CustomerService $customers, MollieGatewayInterface $gwMollie, CancellationValidator $cancellationValidator, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionHistoryHandler $subscriptionHistory, LoggerInterface $logger, SubscriptionRenewing $renewingService, OrderStatusConverter $statusConverter)
    {
        parent::__construct(
            $pluginSettings,
            $repoSubscriptions,
            $subscriptionBuilder,
            $mollieRequestBuilder,
            $customers,
            $gwMollie,
            $cancellationValidator,
            $flowBuilderFactory,
            $flowBuilderEventFactory,
            $subscriptionHistory,
            $logger
        );

        $this->renewingService = $renewingService;
        $this->statusConverter = $statusConverter;
    }


    /**
     * @param string $swSubscriptionId
     * @param string $molliePaymentId
     * @param Context $context
     * @throws Exception
     * @return OrderEntity
     */
    public function renewSubscription(string $swSubscriptionId, string $molliePaymentId, Context $context): OrderEntity
    {
        # we need a custom exception here
        # to avoid errors like "Return value of...not instance of SubscriptionEntity
        try {
            $swSubscription = $this->getRepository()->findById($swSubscriptionId, $context);
        } catch (\Throwable $ex) {
            throw new Exception('Subscription with ID ' . $swSubscriptionId . ' not found in Shopware');
        }

        $settings = $this->getPluginSettings($swSubscription->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription with ID ' . $swSubscriptionId . ' not renewed. Subscriptions are disabled for this Sales Channel');
        }

        # only renew active subscriptions
        # however, if it's the last time, then it's unfortunately already "completed"
        # so we also need to allow this.
        if (!$swSubscription->isRenewingAllowed()) {
            throw new Exception('Subscription is not active and cannot be edited');
        }

        $gateway = $this->getMollieGateway($swSubscription);


        # grab our mollie payment and also the mollie subscription
        $payment = $gateway->getPayment($molliePaymentId);
        $mollieSubscription = $gateway->getSubscription($swSubscription->getMollieId(), $swSubscription->getMollieCustomerId());

        # if this transaction id is somehow NOT from our subscription
        # then do not proceed and throw an error.
        # in DEV mode, we allow this, otherwise we cannot test this!
        if (!$this->isMollieDevMode() && (string)$payment->subscriptionId !== $swSubscription->getMollieId()) {
            throw new \Exception('Warning, trying to renew subscription based on a payment that does not belong to this subscription!');
        }

        # verify if the amount is higher than 0,00
        # we just want to ensure that a "payment method update" does not lead to this webhook (it felt as if it was in 1 case)
        if ((float)$payment->amount->value <= 0) {
            throw new \Exception('Warning, trying to renew subscription based on a 0,00 payment. Mollie should actually not call the renew-webhook for this!');
        }


        $salesChannelSettings = $this->getPluginSettings($swSubscription->getSalesChannelId());

        # It's possible to automatically skip failed payments and avoid that new orders are created. This is a plugin configuration.
        # If skipping is enabled, and the payment status is not approved, then we throw an error and skip the renewal (for this payment attempt).
        if ($salesChannelSettings->isSubscriptionSkipRenewalsOnFailedPayments()) {
            $status = $this->statusConverter->getMolliePaymentStatus($payment);

            if (!MolliePaymentStatus::isApprovedStatus($status)) {
                # let's throw a specific exception, because we need to
                # handle the response for Mollie with 200 OK
                throw new SubscriptionSkippedException($swSubscriptionId, $payment->id);
            }
        }

        # first thing is, we have to update our new paymentAt of our local subscription.
        # we do this immediately because we get the correct data from Mollie anyway
        $this->getRepository()->updateNextPaymentAt(
            $swSubscriptionId,
            (string)$mollieSubscription->nextPaymentDate,
            $context
        );

        # now that we know that we have to renew something,
        # we also need to make sure, that a skipped subscription is "resumed" again.
        # we use skip to show that it's not happening that nothing happens in this interval, but
        # once it's renewed, we make sure its resumed again
        if ($swSubscription->getStatus() === SubscriptionStatus::SKIPPED) {
            $this->getRepository()->updateStatus($swSubscriptionId, SubscriptionStatus::RESUMED, $context);
        }


        $newOrder = $this->renewingService->renewSubscription($swSubscription, $payment, $context);

        # also add a history entry for this subscription
        $this->getStatusHistory()->markRenewed($swSubscription, $context);


        # --------------------------------------------------------------------------------------------------
        # FLOW BUILDER / BUSINESS EVENTS

        # send renewed command
        $event = $this->getFlowBuilderEventFactory()->buildSubscriptionRenewedEvent($swSubscription->getCustomer(), $swSubscription, $context);
        $this->getFlowBuilderDispatcher()->dispatch($event);

        # send original checkout-order-placed of shopware
        $event = new CheckoutOrderPlacedEvent($context, $newOrder, $newOrder->getSalesChannelId());
        $this->getFlowBuilderDispatcher()->dispatch($event);

        # if this was our last renewal, then send out
        # a new event that the subscription has now ended
        if ($mollieSubscription->timesRemaining !== null && $mollieSubscription->timesRemaining <= 0) {
            $event = $this->getFlowBuilderEventFactory()->buildSubscriptionEndedEvent($swSubscription->getCustomer(), $swSubscription, $context);
            $this->getFlowBuilderDispatcher()->dispatch($event);
        }

        # --------------------------------------------------------------------------------------------------

        return $newOrder;
    }
}
