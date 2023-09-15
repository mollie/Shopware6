<?php

namespace Kiener\MolliePayments\Controller\Storefront\Webhook\Sw65;

use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionSkippedException;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Controller\Storefront\Webhook\WebhookControllerBase;
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

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class WebhookController extends WebhookControllerBase
{
}
