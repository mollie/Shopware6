<?php

namespace Kiener\MolliePayments\Controller\Storefront\Webhook;

use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionSkippedException;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Repository\Order\OrderRepository;
use Kiener\MolliePayments\Repository\Order\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class WebhookControllerBase extends StorefrontController
{
    /**
     * @var NotificationFacade
     */
    private $notificationFacade;

    /**
     * @var SubscriptionManager
     */
    private $subscriptions;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $repoOrders;


    /**
     * @param NotificationFacade $notificationFacade
     * @param SubscriptionManager $subscriptionManager
     * @param OrderRepositoryInterface $repoOrders
     * @param LoggerInterface $logger
     */
    public function __construct(NotificationFacade $notificationFacade, SubscriptionManager $subscriptionManager, OrderRepositoryInterface $repoOrders, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->subscriptions = $subscriptionManager;
        $this->notificationFacade = $notificationFacade;
        $this->repoOrders = $repoOrders;
    }

    /**
     * @Route("/mollie/webhook/{swTransactionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.webhook", options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param string $swTransactionId
     * @return JsonResponse
     */
    public function onWebhookReceived(SalesChannelContext $context, string $swTransactionId): JsonResponse
    {
        try {
            $this->notificationFacade->onNotify($swTransactionId, $context->getContext());

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            $this->logger->error(
                'Error in Mollie Webhook for Transaction ' . $swTransactionId,
                [
                    'error' => $ex->getMessage()
                ]
            );

            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $ex->getMessage()
                ],
                422
            );
        }
    }

    /**
     * @Route("/mollie/webhook/subscription/{swSubscriptionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.webhook.subscription", options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param string $swSubscriptionId
     * @param Request $request
     * @param RequestDataBag $requestData
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function onWebhookSubscriptionReceived(string $swSubscriptionId, Request $request, RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        return $this->onWebhookSubscriptionLegacyReceived($swSubscriptionId, $request, $requestData, $context);
    }

    /**
     * @Route("/mollie/webhook/subscription/{swSubscriptionId}/renew", defaults={"csrf_protected"=false}, name="frontend.mollie.webhook.subscription.renew", options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param string $swSubscriptionId
     * @param Request $request
     * @param RequestDataBag $requestData
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function onWebhookSubscriptionLegacyReceived(string $swSubscriptionId, Request $request, RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        # just to improve testing and manual calls, make it is lower case (requirement for entity repositories)
        $swSubscriptionId = strtolower($swSubscriptionId);

        # Mollie automatically sends the new payment id and the subscription id.
        # we do not know that payment yet, because it has just been made by Mollie.
        $molliePaymentId = (string)$requestData->get('id');
        $mollieSubscriptionId = (string)$requestData->get('subscriptionId');

        try {
            $allParams = $request->query->all();

            if (empty($molliePaymentId) && isset($allParams['id'])) {
                $molliePaymentId = (string)$allParams['id'];
            }

            if (empty($mollieSubscriptionId) && isset($allParams['subscriptionId'])) {
                $mollieSubscriptionId = (string)$allParams['subscriptionId'];
            }

            if (empty($molliePaymentId)) {
                throw new \Exception('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
            }


            $subscription = $this->subscriptions->findSubscription($swSubscriptionId, $context->getContext());

            # first search if we already have an existing order
            # with our Mollie ID. If we have one, then this is only an update webhook
            # for that order. If we do not have one, then create a new Shopware order
            $existingOrders = $this->repoOrders->findByMollieId($subscription->getCustomerId(), $molliePaymentId, $context->getContext());

            if ($existingOrders->count() <= 0) {
                $swOrder = $this->subscriptions->renewSubscription($swSubscriptionId, $molliePaymentId, $context->getContext());
            } else {
                $swOrder = $existingOrders->last();
            }


            # now lets grab the latest order transaction of our new order
            /** @var OrderTransactionEntity $latestTransaction */
            $latestTransaction = $this->notificationFacade->getOrderTransactions($swOrder->getId(), $context->getContext())->last();

            # now simply redirect to the official webhook
            # that handles the full order, validates the payment and
            # starts to trigger things.
            return $this->onWebhookReceived($context, $latestTransaction->getId());
        } catch (SubscriptionSkippedException $ex) {
            # if we skip a new subscription, then we need to respond with
            # 200 OK so that Mollie will not try it again.
            $this->logger->info($ex->getMessage());
            return new JsonResponse(
                [
                    'success' => true,
                    'message' => $ex->getMessage()
                ],
                200
            );
        } catch (\Throwable $ex) {
            $this->logger->error(
                'Error in Mollie Webhook for Subscription ' . $swSubscriptionId,
                [
                    'error' => $ex->getMessage(),
                    'request' => [
                        'paymentId' => $molliePaymentId,
                        'subscriptionId' => $mollieSubscriptionId,
                    ]
                ]
            );

            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $ex->getMessage()
                ],
                422
            );
        }
    }
}
