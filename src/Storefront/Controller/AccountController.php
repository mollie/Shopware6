<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Components\Subscription\Page\Account\PageLoader;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Page\Account\Mollie\AccountSubscriptionsPageLoader;
use Kiener\MolliePayments\Service\Subscription\CancelSubscriptionsService;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AccountController extends StorefrontController
{

    /**
     * @var PageLoader
     */
    private $pageLoader;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;


    /**
     * @param PageLoader $pageLoader
     * @param SubscriptionManager $subscriptionManager
     */
    public function __construct(PageLoader $pageLoader, SubscriptionManager $subscriptionManager)
    {
        $this->pageLoader = $pageLoader;
        $this->subscriptionManager = $subscriptionManager;
    }


    /**
     * @LoginRequired()
     * @Route("/account/mollie/subscriptions", name="frontend.account.mollie.subscriptions.page", options={"seo"="false"}, methods={"GET", "POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function subscriptionsList(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $page = $this->pageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront(
            '@Storefront/storefront/page/account/subscriptions/index.html.twig',
            [
                'page' => $page
            ]
        );
    }

    /**
     * @LoginRequired()
     * @Route("/account/mollie/subscriptions/{subscriptionId}/cancel", name="frontend.account.mollie.subscriptions.cancel", methods={"POST"})
     */
    public function cancelSubscription($subscriptionId, SalesChannelContext $context): Response
    {
        $this->subscriptionManager->cancelSubscription($subscriptionId, $context->getContext());

        $this->addFlash(self::SUCCESS,
            $this->trans('molliePayments.subscriptions.account.cancelSubscription',
                ['%1%' => $subscriptionId]
            )
        );

        return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
    }

}
