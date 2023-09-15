<?php

namespace Kiener\MolliePayments\Controller\Storefront\ApplePayDirect\Sw6;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Controller\Storefront\ApplePayDirect\ApplePayDirectControllerBase;
use Kiener\MolliePayments\Repository\Customer\CustomerRepositoryInterface;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ApplePayDirectController extends ApplePayDirectControllerBase
{
}
