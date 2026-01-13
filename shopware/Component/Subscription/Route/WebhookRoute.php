<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute as AbstractPaymentWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute as PaymentWebhookRoute;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class WebhookRoute extends AbstractWebhookRoute
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $transactionRepository
     */
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $transactionRepository,
        #[Autowire(service: PaymentWebhookRoute::class)]
        private AbstractPaymentWebhookRoute $abstractWebhookRoute,
        #[Autowire(service: RenewRoute::class)]
        private AbstractRenewRoute $abstractRenewRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractWebhookRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/mollie/webhook/subscription/{subscriptionId}', name: 'api.mollie.webhook_subscription', methods: ['GET', 'POST'])]
    public function notify(string $subscriptionId, Request $request, Context $context): WebhookResponse
    {
        $molliePaymentId = $request->get('id');
        $subscriptionId = strtolower($subscriptionId);
        $logData = [
            'subscriptionId' => $subscriptionId,
            'data' => [
                'postData' => $request->request->all(),
                'queryData' => $request->query->all(),
            ]
        ];
        $this->logger->info('Subscription webhook received', $logData);

        if ($molliePaymentId === null) {
            $this->logger->error('Subscription webhook was triggered without required data', $logData);
            throw WebhookException::paymentIdNotProvided($subscriptionId);
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . Mollie::EXTENSION . '.id', $molliePaymentId));
        $criteria->addFilter(new EqualsFilter('order.customFields.' . Mollie::EXTENSION . '.swSubscriptionId', $subscriptionId));

        $searchIdResult = $this->transactionRepository->searchIds($criteria, $context);
        $transactionId = $searchIdResult->firstId();

        if ($transactionId !== null) {
            $this->logger->info('Subscription status updated', $logData);

            return $this->abstractWebhookRoute->notify($transactionId, $context);
        }

        $this->logger->info('Subscription will be renewed', $logData);

        return $this->abstractRenewRoute->renew($subscriptionId, $request, $context);
    }
}
