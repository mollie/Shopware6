<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Kiener\MolliePayments\Service\Subscription\CancelSubscriptionsService;

/**
 * @RouteScope(scopes={"api"})
 */
class SubscriptionController extends AbstractController
{
    /**
     * @var CancelSubscriptionsService
     */
    private CancelSubscriptionsService $cancelSubscriptionsService;

    /**
     * Creates a new instance of the onboarding controller.
     *
     * @param CancelSubscriptionsService $cancelSubscriptionsService
     */
    public function __construct(CancelSubscriptionsService $cancelSubscriptionsService)
    {
        $this->cancelSubscriptionsService = $cancelSubscriptionsService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/subscription/cancel",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.subscription.cancel", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancel(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->cancelSubscriptionsService->cancelSubscriptions(
            $data->get('id'),
            $data->get('customerId'),
            $data->get('salesChannelId')
        );
    }
}
