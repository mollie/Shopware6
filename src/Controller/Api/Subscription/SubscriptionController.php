<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Subscription;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
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
        $this->subscriptionManager->cancelSubscription(
            $data->get('id'),
            $context
        );

        return new JsonResponse(['success' => true]);
    }
}
