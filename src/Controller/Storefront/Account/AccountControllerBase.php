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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function __construct(SubscriptionPageLoader $pageLoader, SubscriptionManager $subscriptionManager, LoggerInterface $logger)
    {
        $this->pageLoader = $pageLoader;
        $this->subscriptionManager = $subscriptionManager;
        $this->logger = $logger;
    }

    public function subscriptionsList(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (! $this->isLoggedIn($salesChannelContext)) {
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

    public function updateBilling(string $subscriptionId, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        if (! $this->isLoggedIn($salesChannelContext)) {
            return $this->redirectToLoginPage();
        }

        try {
            $address = $data->get('address', null);

            if (! $address instanceof RequestDataBag) {
                throw new \Exception('Missing address data in request');
            }

            $salutationId = $address->get('salutationId', '');
            $title = $address->get('title', '');
            $firstName = $address->get('firstName', '');
            $lastName = $address->get('lastName', '');
            $company = $address->get('company', '');
            $department = $address->get('department', '');
            $additionalField1 = $address->get('additionalField1', '');
            $additionalField2 = $address->get('additionalField2', '');
            $phoneNumber = $address->get('phoneNumber', '');
            $street = $address->get('street', '');
            $zipcode = $address->get('zipcode', '');
            $city = $address->get('city', '');
            // COUNTRY change not allowed for billing
            $countryStateId = $address->get('countryStateId', '');

            $this->subscriptionManager->updateBillingAddress(
                $subscriptionId,
                $salutationId,
                $title,
                $firstName,
                $lastName,
                $company,
                $department,
                $additionalField1,
                $additionalField2,
                $phoneNumber,
                $street,
                $zipcode,
                $city,
                $countryStateId,
                $salesChannelContext->getContext()
            );

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successUpdateAddress'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        } catch (\Throwable $exception) {
            return $this->routeToErrorPage(
                'molliePayments.subscriptions.account.errorUpdateAddress',
                'Error when updating billing address of subscription ' . $subscriptionId . ': ' . $exception->getMessage()
            );
        }
    }

    public function updateShipping(string $subscriptionId, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        if (! $this->isLoggedIn($salesChannelContext)) {
            return $this->redirectToLoginPage();
        }

        try {
            $address = $data->get('address', null);

            if (! $address instanceof RequestDataBag) {
                throw new \Exception('Missing address data in request');
            }

            $salutationId = $address->get('salutationId', '');
            $title = $address->get('title', '');
            $firstName = $address->get('firstName', '');
            $lastName = $address->get('lastName', '');
            $company = $address->get('company', '');
            $department = $address->get('department', '');
            $additionalField1 = $address->get('additionalField1', '');
            $additionalField2 = $address->get('additionalField2', '');
            $phoneNumber = $address->get('phoneNumber', '');
            $street = $address->get('street', '');
            $zipcode = $address->get('zipcode', '');
            $city = $address->get('city', '');
            // COUNTRY change not allowed for billing
            $countryStateId = $address->get('countryStateId', '');

            $this->subscriptionManager->updateShippingAddress(
                $subscriptionId,
                $salutationId,
                $title,
                $firstName,
                $lastName,
                $company,
                $department,
                $additionalField1,
                $additionalField2,
                $phoneNumber,
                $street,
                $zipcode,
                $city,
                $countryStateId,
                $salesChannelContext->getContext()
            );

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successUpdateAddress'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        } catch (\Throwable $exception) {
            return $this->routeToErrorPage(
                'molliePayments.subscriptions.account.errorUpdateAddress',
                'Error when updating shipping address of subscription ' . $subscriptionId . ': ' . $exception->getMessage()
            );
        }
    }

    public function updatePaymentStart(string $swSubscriptionId, SalesChannelContext $salesChannelContext): Response
    {
        if (! $this->isLoggedIn($salesChannelContext)) {
            return $this->redirectToLoginPage();
        }

        try {
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
        if (! $this->isLoggedIn($salesChannelContext)) {
            return $this->redirectToLoginPage();
        }

        try {
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

    public function pauseSubscription(string $swSubscriptionId, SalesChannelContext $context): Response
    {
        if (! $this->isLoggedIn($context)) {
            return $this->redirectToLoginPage();
        }

        try {
            $this->subscriptionManager->pauseSubscription($swSubscriptionId, $context->getContext());

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successPause'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        } catch (\Throwable $exception) {
            return $this->routeToErrorPage(
                'molliePayments.subscriptions.account.errorPause',
                'Error when pausing subscription ' . $swSubscriptionId . ': ' . $exception->getMessage()
            );
        }
    }

    public function skipSubscription(string $swSubscriptionId, SalesChannelContext $context): Response
    {
        if (! $this->isLoggedIn($context)) {
            return $this->redirectToLoginPage();
        }

        try {
            $this->subscriptionManager->skipSubscription($swSubscriptionId, 1, $context->getContext());

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successSkip'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        } catch (\Throwable $exception) {
            return $this->routeToErrorPage(
                'molliePayments.subscriptions.account.errorSkip',
                'Error when skipping subscription ' . $swSubscriptionId . ': ' . $exception->getMessage()
            );
        }
    }

    public function resumeSubscription(string $swSubscriptionId, SalesChannelContext $context): Response
    {
        if (! $this->isLoggedIn($context)) {
            return $this->redirectToLoginPage();
        }

        try {
            $this->subscriptionManager->resumeSubscription($swSubscriptionId, $context->getContext());

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successResume'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        } catch (\Throwable $exception) {
            return $this->routeToErrorPage(
                'molliePayments.subscriptions.account.errorResume',
                'Error when resuming subscription ' . $swSubscriptionId . ': ' . $exception->getMessage()
            );
        }
    }

    public function cancelSubscription($subscriptionId, SalesChannelContext $context): Response
    {
        if (! $this->isLoggedIn($context)) {
            return $this->redirectToLoginPage();
        }

        try {
            $this->subscriptionManager->cancelSubscription($subscriptionId, $context->getContext());

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.cancelSubscription'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        } catch (\Throwable $exception) {
            return $this->routeToErrorPage(
                'molliePayments.subscriptions.account.errorCancelSubscription',
                'Error when canceling subscription ' . $subscriptionId . ': ' . $exception->getMessage()
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

    private function isLoggedIn(SalesChannelContext $context): bool
    {
        return $context->getCustomer() instanceof CustomerEntity;
    }
}
