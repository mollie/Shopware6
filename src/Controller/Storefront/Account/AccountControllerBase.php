<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Storefront\Account;

use Kiener\MolliePayments\Components\Subscription\Page\Account\SubscriptionPageLoader;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Controller\Storefront\AbstractStoreFrontController;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;

class AccountControllerBase extends AbstractStoreFrontController
{
    /**
     * @var SubscriptionPageLoader
     */
    private $pageLoader;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var LoggerInterface
     */
    private $logger;
    private AccountOverviewPageLoader $overviewPageLoader;

    public function __construct(SubscriptionPageLoader $pageLoader, SubscriptionManager $subscriptionManager,
        AccountOverviewPageLoader $overviewPageLoader,
        LoggerInterface $logger)
    {
        $this->pageLoader = $pageLoader;
        $this->subscriptionManager = $subscriptionManager;
        $this->logger = $logger;
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

    public function updatePaymentStart(string $swSubscriptionId, SalesChannelContext $salesChannelContext): Response
    {
        $customer = $this->getLoggedInCustomer($salesChannelContext);
        if ($customer === null) {
            return $this->redirectToLoginPage();
        }

        try {
            $this->assertOwnership($swSubscriptionId, $customer, $salesChannelContext);

            $redirectUrl = $this->generateUrl(
                'frontend.account.mollie.subscriptions.payment.update-success',
                [
                    'swSubscriptionId' => $swSubscriptionId,
                ],
                UrlGenerator::ABSOLUTE_URL
            );

            $checkoutUrl = $this->subscriptionManager->updatePaymentMethodStart($swSubscriptionId, $redirectUrl, $salesChannelContext->getContext());

            return $this->redirect($checkoutUrl);
        } catch (\Throwable $exception) {
            return $this->routeToErrorPage(
                'molliePayments.subscriptions.account.errorUpdatePayment',
                'Error when updating payment method of subscription ' . $swSubscriptionId . ': ' . $exception->getMessage()
            );
        }
    }

    public function updatePaymentFinish(string $swSubscriptionId, SalesChannelContext $salesChannelContext): Response
    {
        $customer = $this->getLoggedInCustomer($salesChannelContext);
        if ($customer === null) {
            return $this->redirectToLoginPage();
        }

        try {
            $this->assertOwnership($swSubscriptionId, $customer, $salesChannelContext);

            $this->subscriptionManager->updatePaymentMethodConfirm($swSubscriptionId, $salesChannelContext->getContext());

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successUpdatePayment'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        } catch (\Throwable $exception) {
            return $this->routeToErrorPage(
                'molliePayments.subscriptions.account.errorUpdatePayment',
                'Error when updating payment method of subscription ' . $swSubscriptionId . ': ' . $exception->getMessage()
            );
        }
    }

    private function routeToErrorPage(string $errorSnippetKey, string $logMessage): RedirectResponse
    {
        $this->logger->error($logMessage);

        $this->addFlash(self::DANGER, $this->trans($errorSnippetKey));

        return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
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

    /**
     * Ensures that the given customer is the owner of the given subscription.
     * Prevents IDOR: a signed-in customer must not be able to manage another customer's subscription.
     * The caller must have already verified that a customer is signed in (see getLoggedInCustomer()).
     *
     * @throws AccessDeniedHttpException when the subscription does not belong to the given customer
     */
    private function assertOwnership(string $subscriptionId, CustomerEntity $customer, SalesChannelContext $context): void
    {
        $subscription = $this->subscriptionManager->findSubscription($subscriptionId, $context->getContext());

        if ($subscription->getCustomerId() !== $customer->getId()) {
            throw new AccessDeniedHttpException('You are not allowed to access this subscription.');
        }
    }
}
