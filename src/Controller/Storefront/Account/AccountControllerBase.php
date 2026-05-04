<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Storefront\Account;

use Kiener\MolliePayments\Components\Subscription\Page\Account\SubscriptionPageLoader;
use Kiener\MolliePayments\Controller\Storefront\AbstractStoreFrontController;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountControllerBase extends AbstractStoreFrontController
{
    /**
     * @var SubscriptionPageLoader
     */
    private $pageLoader;

    private AccountOverviewPageLoader $overviewPageLoader;

    public function __construct(SubscriptionPageLoader $pageLoader, AccountOverviewPageLoader $overviewPageLoader)
    {
        $this->pageLoader = $pageLoader;
        $this->overviewPageLoader = $overviewPageLoader;
    }

    public function subscriptionsList(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if ($this->getLoggedInCustomer($salesChannelContext) === null) {
            return $this->redirectToLoginPage();
        }

        $page = $this->pageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront(
            '@Storefront/storefront/page/account/subscriptions/index.html.twig',
            [
                'page' => $page,
            ]
        );
    }

    public function mandatesList(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $customer = $this->getLoggedInCustomer($salesChannelContext);
        if ($customer === null) {
            return $this->redirectToLoginPage();
        }

        $page = $this->overviewPageLoader->load($request, $salesChannelContext, $customer);

        return $this->renderStorefront(
            '@Storefront/storefront/page/account/mandates/index.html.twig',
            [
                'page' => $page,
            ]
        );
    }

    private function redirectToLoginPage(): RedirectResponse
    {
        return new RedirectResponse($this->generateUrl('frontend.account.login'), 302);
    }

    private function getLoggedInCustomer(SalesChannelContext $context): ?CustomerEntity
    {
        $customer = $context->getCustomer();

        return $customer instanceof CustomerEntity ? $customer : null;
    }
}
