<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Webhook\Sw65;

use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionSkippedException;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Controller\Api\Webhook\WebhookControllerBase;
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

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=false, "auth_enabled"=false})
 */
class WebhookController extends WebhookControllerBase
{
}
