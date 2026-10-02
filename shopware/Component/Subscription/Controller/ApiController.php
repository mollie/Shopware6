<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Controller;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandlerInterface;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final class ApiController extends AbstractController
{
    private const EDITABLE_STATUSES = [
        SubscriptionStatus::PENDING,
        SubscriptionStatus::ACTIVE,
        SubscriptionStatus::SUSPENDED,
        SubscriptionStatus::COMPLETED,
        SubscriptionStatus::CANCELED,
        SubscriptionStatus::PAUSED,
        SubscriptionStatus::RESUMED,
        SubscriptionStatus::SKIPPED,
    ];

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: SubscriptionActionHandler::class)]
        private readonly SubscriptionActionHandlerInterface $actionHandler,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
    ) {
    }

    #[Route(path: '/api/_action/mollie/subscriptions/pause', name: 'api.action.mollie.subscription.pause', defaults: ['action' => 'pause', PlatformRequest::ATTRIBUTE_ACL => ['mollie_subscription:update']], methods: ['POST'])]
    #[Route(path: '/api/_action/mollie/subscriptions/resume', name: 'api.action.mollie.subscription.resume', defaults: ['action' => 'resume', PlatformRequest::ATTRIBUTE_ACL => ['mollie_subscription:update']], methods: ['POST'])]
    #[Route(path: '/api/_action/mollie/subscriptions/skip', name: 'api.action.mollie.subscription.skip', defaults: ['action' => 'skip', PlatformRequest::ATTRIBUTE_ACL => ['mollie_subscription:update']], methods: ['POST'])]
    #[Route(path: '/api/_action/mollie/subscriptions/cancel', name: 'api.action.mollie.subscription.cancel', defaults: ['action' => 'cancel', PlatformRequest::ATTRIBUTE_ACL => ['mollie_subscription_custom:cancel']], methods: ['POST'])]
    #[Route(path: '/api/_action/mollie/subscriptions/{action}', name: 'api.action.mollie.subscription.changeState', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['mollie_subscription:update', 'mollie_subscription_custom:cancel']], methods: ['POST'])]
    public function changeState(Request $request, Context $context): Response
    {
        $subscriptionId = (string) $request->request->get('id');
        $action = $request->attributes->get('action');
        $status = 500;
        $success = false;
        $data = [];
        try {
            $response = $this->actionHandler->handle($action, $subscriptionId, $context);
            $data = ['subscription' => $response->toArray()];
            $status = 200;
            $success = true;
        } catch (\Throwable $exception) {
            $data['error'] = $exception->getMessage();
        }

        $data['success'] = $success;

        return new JsonResponse($data, $status);
    }

    #[Route(path: '/api/_action/mollie/subscriptions/{subscriptionId}/edit', name: 'api.action.mollie.subscription.edit', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['mollie_subscription:update']], methods: ['POST'])]
    public function edit(string $subscriptionId, Request $request, Context $context): JsonResponse
    {
        $subscription = $this->subscriptionRepository->search(new Criteria([$subscriptionId]), $context)->getEntities()->first();
        if (! $subscription instanceof SubscriptionEntity) {
            return $this->buildErrorResponse(sprintf('Subscription with id %s was not found', $subscriptionId), Response::HTTP_NOT_FOUND);
        }

        $status = (string) $request->request->get('status', $subscription->getStatus());
        $mollieId = trim((string) $request->request->get('mollieId', $subscription->getMollieId()));
        $orderId = (string) $request->request->get('orderId', $subscription->getOrderId());

        if (! in_array($status, self::EDITABLE_STATUSES, true)) {
            return $this->buildErrorResponse(sprintf('Subscription status %s is not valid', $status), Response::HTTP_BAD_REQUEST);
        }

        $upsert = ['id' => $subscriptionId];
        $historyEntries = [];

        if ($status !== $subscription->getStatus()) {
            $upsert['status'] = $status;
            $historyEntries[] = [
                'statusFrom' => $subscription->getStatus(),
                'statusTo' => $status,
                'comment' => 'status changed',
                'mollieId' => $mollieId,
            ];
        }

        if ($mollieId !== $subscription->getMollieId()) {
            $upsert['mollieId'] = $mollieId;
            $historyEntries[] = [
                'statusFrom' => '',
                'statusTo' => '',
                'comment' => 'mollie subscription id changed',
                'mollieId' => $mollieId,
            ];
        }

        if ($orderId !== $subscription->getOrderId()) {
            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('transactions.stateMachineState');

            $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();
            if (! $order instanceof OrderEntity) {
                return $this->buildErrorResponse(sprintf('Order with id %s was not found', $orderId), Response::HTTP_NOT_FOUND);
            }

            $transactions = new MollieOrderTransactionCollection($order->getTransactions());
            $molliePayment = $transactions->getCurrentOrderTransaction()?->getCustomFields()[Mollie::EXTENSION] ?? [];

            $upsert['orderId'] = $order->getId();
            $upsert['orderVersionId'] = Defaults::LIVE_VERSION;
            $upsert['mandateId'] = $molliePayment['mandateId'] ?? $subscription->getMandateId();
            $upsert['mollieCustomerId'] = $molliePayment['customerId'] ?? $subscription->getMollieCustomerId();
            $historyEntries[] = [
                'statusFrom' => '',
                'statusTo' => '',
                'comment' => 'order assigned',
                'mollieId' => $mollieId,
            ];
        }

        if (count($historyEntries) === 0) {
            return new JsonResponse(['success' => true]);
        }

        $upsert['historyEntries'] = $historyEntries;
        $this->subscriptionRepository->upsert([$upsert], $context);

        return new JsonResponse(['success' => true]);
    }

    private function buildErrorResponse(string $error, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'errors' => [$error],
        ], $status);
    }
}
