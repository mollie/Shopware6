<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Subscription;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Traits\Api\ApiTrait;
use PHPUnit\Framework\MockObject\Api;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class SubscriptionController extends AbstractController
{
    use ApiTrait;


    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;


    /**
     * @param SubscriptionManager $subscriptionManager
     */
    public function __construct(SubscriptionManager $subscriptionManager)
    {
        $this->subscriptionManager = $subscriptionManager;
    }

    /**
     * @Route("/api/_action/mollie/subscriptions/cancel", defaults={"auth_enabled"=true}, name="api.action.mollie.subscription.cancel", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancel(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $this->subscriptionManager->cancelSubscription(
                $data->get('id'),
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    /**
     * @Route("/api/_action/mollie/subscriptions/pause", defaults={"auth_enabled"=true}, name="api.action.mollie.subscription.pause", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function pause(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $this->subscriptionManager->pauseSubscription(
                $data->get('id'),
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    /**
     * @Route("/api/_action/mollie/subscriptions/resume", defaults={"auth_enabled"=true}, name="api.action.mollie.subscription.resume", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function resume(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $this->subscriptionManager->resumeSubscription(
                $data->get('id'),
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    /**
     * @Route("/api/_action/mollie/subscriptions/skip", defaults={"auth_enabled"=true}, name="api.action.mollie.subscription.skip", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function skip(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $this->subscriptionManager->skipSubscription(
                $data->get('id'),
                1,
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }
}
