<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover\SubscriptionRemover;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

class UpdatePaymentAction extends BaseAction
{
    /**
     * @var MollieOrderPriceBuilder
     */
    private $priceBuilder;

    /**
     * @var RoutingBuilder
     */
    private $routingBuilder;

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
     * @param MollieOrderPriceBuilder $priceBuilder
     * @param RoutingBuilder $routingBuilder
     * @param OrderStatusConverter $orderStatusConverter
     * @throws Exception
     */
    public function __construct(SettingsService $pluginSettings, SubscriptionRepository $repoSubscriptions, SubscriptionBuilder $subscriptionBuilder, MollieDataBuilder $mollieRequestBuilder, CustomerService $customers, MollieGatewayInterface $gwMollie, CancellationValidator $cancellationValidator, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionHistoryHandler $subscriptionHistory, LoggerInterface $logger, MollieOrderPriceBuilder $priceBuilder, RoutingBuilder $routingBuilder, OrderStatusConverter $orderStatusConverter)
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

        $this->priceBuilder = $priceBuilder;
        $this->routingBuilder = $routingBuilder;
        $this->statusConverter = $orderStatusConverter;
    }

    /**
     * @param string $subscriptionId
     * @param string $redirectUrl
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return string
     */
    public function updatePaymentMethodStart(string $subscriptionId, string $redirectUrl, Context $context): string
    {
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        # --------------------------------------------------------------------------------------------------

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription Payment Method cannot be updated. Subscriptions are disabled for this Sales Channel');
        }

        if (!$subscription->isUpdatePaymentAllowed()) {
            throw new Exception('Updating the payment method of the subscription is not possible because of its current status!');
        }

        # --------------------------------------------------------------------------------------------------

        # first load our customer ID
        # every subscription customer should have already a Mollie customer ID
        $customerStruct = $this->getCustomers()->getCustomerStruct($subscription->getCustomerId(), $context);
        $customerId = $customerStruct->getCustomerId((string)$settings->getProfileId(), $settings->isTestMode());

        # now create our payment.
        # it's important to use a sequenceType first to allow 0,00 amount payment.
        # this will be used to process the payment and get/create a new mandate inside the Mollie API systems.
        $gateway = $this->getMollieGateway($subscription);

        # for headless, we might provide a separate return URL
        # for the storefront, we build our correct one
        if (empty($redirectUrl)) {
            $redirectUrl = $this->routingBuilder->buildSubscriptionPaymentUpdatedReturnUrl($subscriptionId);
        }

        $webhookUrl = $this->routingBuilder->buildSubscriptionPaymentUpdatedWebhook($subscriptionId);


        $payload = [
            'sequenceType' => 'first',
            'customerId' => $customerId,
            'method' => SubscriptionRemover::ALLOWED_METHODS,
            'amount' => $this->priceBuilder->build(0, 'EUR'),
            'description' => 'Update Subscription Payment: ' . $subscription->getDescription(),
            'redirectUrl' => $redirectUrl,
        ];

        # storefront does not have a webhook
        # it's done immediately on sync
        if (!empty($webhookUrl)) {
            $payload['webhookUrl'] = $webhookUrl;
        }


        $payment = $gateway->createPayment($payload);


        # now update our metadata and set the temporary transaction ID.
        # we need this in the redirectURL to verify if this
        # payment was successful or if it failed.
        $meta = $subscription->getMetadata();
        $meta->setTmpTransaction($payment->id);
        $this->getRepository()->updateSubscriptionMetadata($subscription->getId(), $meta, $context);

        # simply return the checkoutURL to redirect the customer
        return (string)$payment->getCheckoutUrl();
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function updatePaymentMethodConfirm(string $subscriptionId, Context $context): void
    {
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        if ($subscription->getStatus() !== SubscriptionStatus::ACTIVE && $subscription->getStatus() !== SubscriptionStatus::RESUMED) {
            throw new Exception('Subscription is not active and cannot be edited');
        }

        # load our latest tmp_transaction ID that was used
        # to initialize the payment of the update.
        # we have to verify if it was indeed successful
        $latestTransactionId = $subscription->getMetadata()->getTmpTransaction();

        if (empty($latestTransactionId)) {
            throw new Exception('No temporary transaction existing for this subscription');
        }

        # load our Mollie Payment with this
        # temporary transaction ID
        $gateway = $this->getMollieGateway($subscription);


        $payment = $gateway->getPayment($latestTransactionId);

        # now verify if the payment was indeed
        # successful and that our subscription mandate can be updated
        # based on the mandateId in this payment
        $status = $this->statusConverter->getMolliePaymentStatus($payment);
        if (!MolliePaymentStatus::isApprovedStatus($status)) {
            throw new Exception('Payment failed when updating subscription mandate. Payment ' . $payment->id . ' for new mandate was not successful!');
        }

        # now update our Mollie subscription
        # with the new mandateId of the approved payment
        $gateway->updateSubscription(
            $subscription->getMollieId(),
            $subscription->getMollieCustomerId(),
            (string)$payment->mandateId
        );

        $mandateId = (string)$payment->mandateId;

        # after updating our mandate ID,
        # make sure to remove our temporary transaction ID again
        $meta = $subscription->getMetadata();
        $meta->setTmpTransaction('');
        $this->getRepository()->updateSubscriptionMetadata($subscription->getId(), $meta, $context);

        $this->getRepository()->updateMandate($subscriptionId, $mandateId, $context);


        # also add a history entry for this subscription
        $this->getStatusHistory()->markPaymentUpdated($subscription, $mandateId, $context);
    }
}
