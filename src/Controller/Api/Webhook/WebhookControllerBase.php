<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Webhook;

use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionSkippedException;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Controller\Storefront\Webhook\NotificationFacade;
use Kiener\MolliePayments\Repository\Order\OrderRepository;
use Kiener\MolliePayments\Repository\Order\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class WebhookControllerBase extends AbstractController
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
     * @var OrderRepositoryInterface
     */
    private $repoOrders;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param NotificationFacade $notificationFacade
     * @param SubscriptionManager $subscriptions
     * @param OrderRepositoryInterface $repoOrders
     * @param LoggerInterface $logger
     */
    public function __construct(NotificationFacade $notificationFacade, SubscriptionManager $subscriptions, OrderRepositoryInterface $repoOrders, LoggerInterface $logger)
    {
        $this->notificationFacade = $notificationFacade;
        $this->subscriptions = $subscriptions;
        $this->repoOrders = $repoOrders;
        $this->logger = $logger;
    }


    /**
     * @Route("/api/mollie/webhook/{swTransactionId}", name="api.mollie.webhook", methods={"GET", "POST"})
     *
     * @param string $swTransactionId
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookAction(string $swTransactionId, Request $request, Context $context): JsonResponse
    {
        try {
            $this->notificationFacade->onNotify($swTransactionId, $context);

            return new JsonResponse([
                'success' => true
            ]);
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
     * @Route("/api/v{version}/mollie/webhook/{swTransactionId}", name="api.mollie.webhook-legacy", methods={"GET", "POST"})
     *
     * @param string $swTransactionId
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookLegacyAction(string $swTransactionId, Request $request, Context $context): JsonResponse
    {
        return $this->webhookAction($swTransactionId, $request, $context);
    }


    /**
     * @Route("/api/mollie/webhook/subscription/{swSubscriptionId}", name="api.mollie.webhook_subscription", methods={"GET", "POST"})
     *
     * @param string $swSubscriptionId
     * @param Request $request
     * @param RequestDataBag $requestData
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookSubscriptionAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
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

            $subscription = $this->subscriptions->findSubscription($swSubscriptionId, $context);

            # first search if we already have an existing order
            # with our Mollie ID. If we have one, then this is only an update webhook
            # for that order. If we do not have one, then create a new Shopware order
            $existingOrders = $this->repoOrders->findByMollieId($subscription->getCustomerId(), $molliePaymentId, $context);

            if ($existingOrders->count() <= 0) {
                $swOrder = $this->subscriptions->renewSubscription($swSubscriptionId, $molliePaymentId, $context);
            } else {
                $swOrder = $existingOrders->last();
            }

            # now lets grab the latest order transaction of our new order
            /** @var OrderTransactionEntity $latestTransaction */
            $latestTransaction = $this->notificationFacade->getOrderTransactions($swOrder->getId(), $context)->last();

            # now simply redirect to the official webhook
            # that handles the full order, validates the payment and
            # starts to trigger things.
            return $this->webhookAction($latestTransaction->getId(), $request, $context);
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

    /**
     * @Route("/api/v{version}/mollie/webhook/subscription/{swSubscriptionId}", name="api.mollie.webhook_subscription-legacy", methods={"GET", "POST"})
     *
     * @param string $swSubscriptionId
     * @param Request $request
     * @param RequestDataBag $requestData
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookSubscriptionLegacyAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        return $this->webhookSubscriptionAction($swSubscriptionId, $request, $requestData, $context);
    }

    /**
     * @Route("/api/mollie/webhook/subscription/{swSubscriptionId}/renew", name="api.mollie.webhook_subscription_renew", methods={"GET", "POST"})
     *
     * @param string $swSubscriptionId
     * @param Request $request
     * @param RequestDataBag $requestData
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookSubscriptionRenewAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        return $this->webhookSubscriptionAction($swSubscriptionId, $request, $requestData, $context);
    }

    /**
     * @Route("/api/v{version}/mollie/webhook/subscription/{swSubscriptionId}/renew", name="api.mollie.webhook_subscription_renew-legacy", methods={"GET", "POST"})
     *
     * @param string $swSubscriptionId
     * @param Request $request
     * @param RequestDataBag $requestData
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookSubscriptionRenewLegacyAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        return $this->webhookSubscriptionAction($swSubscriptionId, $request, $requestData, $context);
    }


    /**
     * @Route("/api/mollie/webhook/subscription/{swSubscriptionId}/mandate/update", name="api.mollie.webhook_subscription_paymentmethod", methods={"GET", "POST"})
     *
     * @param string $swSubscriptionId
     * @param Request $request
     * @param RequestDataBag $requestData
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookSubscriptionMandateUpdatedAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        # just to improve testing and manual calls, make it is lower case (requirement for entity repositories)
        $swSubscriptionId = strtolower($swSubscriptionId);

        try {
            $this->subscriptions->updatePaymentMethodConfirm($swSubscriptionId, $context);

            return new JsonResponse(['success' => true,], 200);
        } catch (\Throwable $ex) {
            $this->logger->error(
                'Error in Mollie Webhook for Subscription payment method update ' . $swSubscriptionId,
                [
                    'error' => $ex->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $ex->getMessage()], 422);
        }
    }

    /**
     * @Route("/api/v{version}/mollie/webhook/subscription/{swSubscriptionId}/mandate/update", name="api.mollie.webhook_subscription_paymentmethod-legacy", methods={"GET", "POST"})
     *
     * @param string $swSubscriptionId
     * @param Request $request
     * @param RequestDataBag $requestData
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookSubscriptionMandateUpdatedLegacyAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        return $this->webhookSubscriptionMandateUpdatedAction($swSubscriptionId, $request, $requestData, $context);
    }
}
