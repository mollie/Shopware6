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
use Mollie\Shopware\Component\Subscription\CopyOrderService;
use Mollie\Shopware\Component\Subscription\CopyOrderServiceInterface;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionEndedEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionRenewedEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Component\Subscription\SubscriptionDataServiceInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
final class RenewRoute extends AbstractRenewRoute
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: SubscriptionDataService::class)]
        private readonly SubscriptionDataServiceInterface $subscriptionDataService,
        #[Autowire(service: CopyOrderService::class)]
        private readonly CopyOrderServiceInterface $copyOrderService,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: PaymentWebhookRoute::class)]
        private readonly AbstractPaymentWebhookRoute $paymentWebhookRoute,
        private readonly SubscriptionActionHandler $subscriptionActionHandler,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
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
        $logData = [
            'subscriptionId' => $subscriptionId,
            'molliePaymentId' => $molliePaymentId,
            'data' => [
                'postData' => $request->request->all(),
                'queryData' => $request->query->all(),
            ]
        ];

        $this->logger->debug('Subscription renew requested', $logData);

        if (strlen($molliePaymentId) === 0) {
            $this->logger->error('Subscription renew was triggered without required data', $logData);
            throw WebhookException::paymentIdNotProvided($subscriptionId);
        }

        $subscriptionData = $this->subscriptionDataService->findById($subscriptionId,$context);
        $subscriptionEntity = $subscriptionData->getSubscription();
        $order = $subscriptionData->getOrder();
        $customer = $subscriptionData->getCustomer();

        $salesChannelId = $subscriptionEntity->getSalesChannelId();
        $mollieCustomerId = $subscriptionEntity->getMollieCustomerId();
        $mollieSubscriptionId = $subscriptionEntity->getMollieId();
        $shopwareSubscriptionStatus = SubscriptionStatus::from($subscriptionEntity->getStatus());

        $afterRenewalAction = $shopwareSubscriptionStatus->getAction();

        $orderNumber = (string) $order->getOrderNumber();

        $logData = array_merge($logData, [
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
            'mollieCustomerId' => $mollieCustomerId,
            'mollieSubscriptionId' => $mollieSubscriptionId,
            'shopwareSubscriptionStatus' => $shopwareSubscriptionStatus->value,
        ]);

        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelId);

        if (! $subscriptionSettings->isEnabled()) {
            $this->logger->error('Subscription renew not possible, subscriptions are disabled for this sales channel', $logData);
            throw RenewException::subscriptionsDisabled($subscriptionId, $salesChannelId);
        }

        $this->logger->info('Subscription renew - Start', $logData);
        $subscription = $this->subscriptionGateway->getSubscription($mollieSubscriptionId, $mollieCustomerId, $orderNumber, $salesChannelId);
        $molliePayment = $this->mollieGateway->getPayment($molliePaymentId, $orderNumber, $salesChannelId);
        $environmentSettings = $this->settingsService->getEnvironmentSettings();

        if (! $environmentSettings->isDevMode() && $molliePayment->getSubscriptionId() !== $subscription->getId()) {
            $this->logger->error('The provided mollie payments ID does not belong to the subscription', $logData);
            throw RenewException::invalidPaymentId($subscriptionId, $molliePaymentId);
        }

        if ($molliePayment->getStatus()->isFailed() && $subscriptionSettings->isSkipIfFailed()) {
            return new WebhookResponse($molliePayment);
        }

        $subscriptionHistories = [];

        if ($shopwareSubscriptionStatus->isInterrupted()) {
            $this->logger->info('Subscription was skipped or paused, changed to resumed', $logData);
            $subscriptionHistories[] = [
                'statusFrom' => $shopwareSubscriptionStatus->value,
                'statusTo' => SubscriptionStatus::RESUMED->value,
                'mollieId' => $subscription->getId(),
                'comment' => 'resumed'
            ];
            $shopwareSubscriptionStatus = SubscriptionStatus::RESUMED;
        }

        $transaction = $this->copyOrderService->copy($subscriptionData,$molliePayment,$context);

        $today = new \DateTime();
        $nextPaymentDate = $subscription->getNextPaymentDate() ?? $today;
        $nextPaymentDate = max($nextPaymentDate, $today);

        $subscriptionHistories[] = [
            'statusFrom' => $shopwareSubscriptionStatus->value,
            'statusTo' => SubscriptionStatus::ACTIVE->value,
            'mollieId' => $subscription->getId(),
            'comment' => 'renewed'
        ];

        $this->subscriptionRepository->upsert([[
            'id' => $subscriptionId,
            'mandateId' => (string) $molliePayment->getMandateId(),
            'nextPaymentAt' => $nextPaymentDate->format('Y-m-d'),
            'historyEntries' => $subscriptionHistories
        ]], $context);

        $renewEvent = new SubscriptionRenewedEvent($subscriptionEntity, $customer, $context);
        $this->eventDispatcher->dispatch($renewEvent);

        if ($subscription->getStatus() === SubscriptionStatus::COMPLETED) {
            $this->logger->info('Subscription had limited amount, it is finished completely now', $logData);
            $endedEvent = new SubscriptionEndedEvent($subscriptionEntity, $customer, $context);
            $this->eventDispatcher->dispatch($endedEvent);
        }

        if ($afterRenewalAction !== null) {
            try {
                $this->logger->info('Subscription was cancelled after renewal, changed to cancelled', $logData);
                $this->subscriptionActionHandler->handle($afterRenewalAction, $subscriptionId, $context);
            } catch (\Throwable $exception) {
                $logData['message'] = $exception->getMessage();
                $this->logger->error('Failed to execute after renewal action: ' . $afterRenewalAction, $logData);
            }
        }

        $this->logger->info('Subscription renew - Finished, call Webhook', $logData);

        return $this->paymentWebhookRoute->notify($transaction->getId(), $context);
    }
}
