<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute as AbstractPaymentWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute as PaymentWebhookRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Action\RenewAction;
use Mollie\Shopware\Component\Subscription\RenewalOrderCreator;
use Mollie\Shopware\Component\Subscription\SubscriptionAddressSyncer;
use Mollie\Shopware\Component\Subscription\SubscriptionAddressSyncerInterface;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Component\Subscription\SubscriptionDataServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
final class RenewRoute extends AbstractRenewRoute
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: SubscriptionDataService::class)]
        private readonly SubscriptionDataServiceInterface $subscriptionDataService,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: SubscriptionAddressSyncer::class)]
        private readonly SubscriptionAddressSyncerInterface $addressSyncer,
        private readonly RenewalOrderCreator $renewalOrderCreator,
        private readonly RenewAction $renewAction,
        #[Autowire(service: PaymentWebhookRoute::class)]
        private readonly AbstractPaymentWebhookRoute $paymentWebhookRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractRenewRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/mollie/webhook/subscription/{subscriptionId}/renew', name: 'api.mollie.webhook.subscription.renew', methods: ['GET', 'POST'])]
    public function renew(string $subscriptionId, Request $request, Context $context): WebhookResponse
    {
        $subscriptionId = strtolower($subscriptionId);
        $molliePaymentId = (string) $request->get('id', '');

        if (strlen($molliePaymentId) === 0) {
            $this->logger->error('Subscription renew was triggered without required data', [
                'subscriptionId' => $subscriptionId,
            ]);
            throw WebhookException::paymentIdNotProvided($subscriptionId);
        }

        $subscriptionData = $this->subscriptionDataService->findById($subscriptionId, $context);
        $subscription = $subscriptionData->getSubscription();
        $order = $subscriptionData->getOrder();
        $customer = $subscriptionData->getCustomer();

        $salesChannelId = $subscription->getSalesChannelId();
        $orderNumber = (string) $order->getOrderNumber();
        $previousStatus = SubscriptionStatus::from($subscription->getStatus());
        $afterRenewalAction = $previousStatus->getAction();

        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelId);
        if (! $subscriptionSettings->isEnabled()) {
            throw RenewException::subscriptionsDisabled($subscriptionId, $salesChannelId);
        }

        $mollieSubscription = $this->subscriptionGateway->getSubscription(
            $subscription->getMollieId(),
            $subscription->getMollieCustomerId(),
            $orderNumber,
            $salesChannelId
        );
        $molliePayment = $this->mollieGateway->getPayment($molliePaymentId, $orderNumber, $salesChannelId);

        $environmentSettings = $this->settingsService->getEnvironmentSettings();
        if (! $environmentSettings->isDevMode() && $molliePayment->getSubscriptionId() !== $mollieSubscription->getId()) {
            throw RenewException::invalidPaymentId($subscriptionId, $molliePaymentId);
        }

        if ($molliePayment->getStatus()->isFailed() && $subscriptionSettings->isSkipIfFailed()) {
            return new WebhookResponse($molliePayment);
        }

        $intervalKey = (string) $subscription->getMetadata()->getInterval();
        $addresses = $this->addressSyncer->syncFromSubscription($subscription, $context);

        $transaction = $this->renewalOrderCreator->create(
            $order,
            $subscriptionId,
            $intervalKey,
            $addresses,
            $molliePayment,
            $context
        );

        $this->renewAction->execute(
            $subscription,
            $mollieSubscription,
            $molliePayment,
            $previousStatus,
            $customer,
            $afterRenewalAction,
            $context
        );

        return $this->paymentWebhookRoute->notify($transaction->getId(), $context);
    }
}
