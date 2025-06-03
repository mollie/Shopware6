<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Webhook;

use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionSkippedException;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Controller\Storefront\Webhook\NotificationFacade;
use Kiener\MolliePayments\Repository\OrderRepository;
use Kiener\MolliePayments\Repository\OrderTransactionRepository;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
     * @var OrderRepository
     */
    private $repoOrders;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderTransactionRepository
     */
    private $repoOrderTransactions;

    public function __construct(NotificationFacade $notificationFacade, SubscriptionManager $subscriptions, OrderRepository $repoOrders, OrderTransactionRepository $repoOrderTransactions, LoggerInterface $logger)
    {
        $this->notificationFacade = $notificationFacade;
        $this->subscriptions = $subscriptions;
        $this->repoOrders = $repoOrders;
        $this->repoOrderTransactions = $repoOrderTransactions;
        $this->logger = $logger;
    }

    public function webhookAction(string $swTransactionId, Request $request, Context $context): JsonResponse
    {
        try {
            $this->notificationFacade->onNotify($swTransactionId, $context);

            return new JsonResponse([
                'success' => true,
            ]);
        } catch (\Throwable $ex) {
            $this->logger->error(
                'Error in Mollie Webhook for Transaction ' . $swTransactionId,
                [
                    'error' => $ex->getMessage(),
                ]
            );

            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $ex->getMessage(),
                ],
                422
            );
        }
    }

    public function webhookLegacyAction(string $swTransactionId, Request $request, Context $context): JsonResponse
    {
        return $this->webhookAction($swTransactionId, $request, $context);
    }

    public function webhookSubscriptionAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        // just to improve testing and manual calls, make it is lower case (requirement for entity repositories)
        $swSubscriptionId = strtolower($swSubscriptionId);

        // Mollie automatically sends the new payment id and the subscription id.
        // we do not know that payment yet, because it has just been made by Mollie.
        $molliePaymentId = (string) $requestData->get('id');
        $mollieSubscriptionId = (string) $requestData->get('subscriptionId');

        try {
            $allParams = $request->query->all();

            if (empty($molliePaymentId) && isset($allParams['id'])) {
                $molliePaymentId = (string) $allParams['id'];
            }

            if (empty($mollieSubscriptionId) && isset($allParams['subscriptionId'])) {
                $mollieSubscriptionId = (string) $allParams['subscriptionId'];
            }

            if (empty($molliePaymentId)) {
                throw new \Exception('Please provide a Mollie Payment ID with the payment that has been done for this subscription');
            }

            $subscription = $this->subscriptions->findSubscription($swSubscriptionId, $context);

            // first search if we already have an existing order
            // with our Mollie ID. If we have one, then this is only an update webhook
            // for that order. If we do not have one, then create a new Shopware order
            $existingOrders = $this->repoOrders->findByMollieId($subscription->getCustomerId(), $molliePaymentId, $context);

            if ($existingOrders->count() <= 0) {
                $swOrder = $this->subscriptions->renewSubscription($swSubscriptionId, $molliePaymentId, $context);
            } else {
                /** @var OrderEntity $swOrder */
                $swOrder = $existingOrders->last();
            }

            // now lets grab the latest order transaction of our new order
            $latestTransaction = $this->repoOrderTransactions->getLatestOrderTransaction($swOrder->getId(), $context);

            // now simply redirect to the official webhook
            // that handles the full order, validates the payment and
            // starts to trigger things.
            return $this->webhookAction($latestTransaction->getId(), $request, $context);
        } catch (SubscriptionSkippedException $ex) {
            // if we skip a new subscription, then we need to respond with
            // 200 OK so that Mollie will not try it again.
            $this->logger->info($ex->getMessage());

            return new JsonResponse(
                [
                    'success' => true,
                    'message' => $ex->getMessage(),
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
                    ],
                ]
            );

            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $ex->getMessage(),
                ],
                422
            );
        }
    }

    public function webhookSubscriptionLegacyAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        return $this->webhookSubscriptionAction($swSubscriptionId, $request, $requestData, $context);
    }

    public function webhookSubscriptionRenewAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        return $this->webhookSubscriptionAction($swSubscriptionId, $request, $requestData, $context);
    }

    public function webhookSubscriptionRenewLegacyAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        return $this->webhookSubscriptionAction($swSubscriptionId, $request, $requestData, $context);
    }

    public function webhookSubscriptionMandateUpdatedAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        // just to improve testing and manual calls, make it is lower case (requirement for entity repositories)
        $swSubscriptionId = strtolower($swSubscriptionId);

        try {
            $this->subscriptions->updatePaymentMethodConfirm($swSubscriptionId, $context);

            return new JsonResponse(['success' => true], 200);
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

    public function webhookSubscriptionMandateUpdatedLegacyAction(string $swSubscriptionId, Request $request, RequestDataBag $requestData, Context $context): JsonResponse
    {
        return $this->webhookSubscriptionMandateUpdatedAction($swSubscriptionId, $request, $requestData, $context);
    }
}
