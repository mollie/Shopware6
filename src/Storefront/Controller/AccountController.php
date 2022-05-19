<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Components\Subscription\Page\Account\SubscriptionPageLoader;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Page\Account\Mollie\AccountSubscriptionsPageLoader;
use Kiener\MolliePayments\Service\Subscription\CancelSubscriptionsService;
use Shopware\Core\Framework\Context;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
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


    /**
     * @param SubscriptionPageLoader $pageLoader
     * @param SubscriptionManager $subscriptionManager
     * @param LoggerInterface $logger
     */
    public function __construct(SubscriptionPageLoader $pageLoader, SubscriptionManager $subscriptionManager, LoggerInterface $logger)
    {
        $this->pageLoader = $pageLoader;
        $this->subscriptionManager = $subscriptionManager;
        $this->logger = $logger;
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
     * @Route("/account/mollie/subscriptions/{subscriptionId}/billing/update", name="frontend.account.mollie.subscriptions.billing.update", methods={"POST"})
     *
     * @param string $subscriptionId
     * @param RequestDataBag $data
     * @param SalesChannelContext $salesChannelContext
     * @return Response
     */
    public function updateBilling(string $subscriptionId, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        try {

            $address = $data->get('address', null);

            if (!$address instanceof RequestDataBag) {
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
            # COUNTRY change not allowed for billing
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

            $this->logger->error('Error when updating billing address of subscription ' . $subscriptionId . ': ' . $exception->getMessage());

            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorUpdateAddress'));
            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        }
    }

    /**
     * @LoginRequired()
     * @Route("/account/mollie/subscriptions/{subscriptionId}/shipping/update", name="frontend.account.mollie.subscriptions.shipping.update", methods={"POST"})
     *
     * @param string $subscriptionId
     * @param RequestDataBag $data
     * @param SalesChannelContext $salesChannelContext
     * @return Response
     */
    public function updateShipping(string $subscriptionId, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        try {

            $address = $data->get('address', null);

            if (!$address instanceof RequestDataBag) {
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
            # COUNTRY change not allowed for billing
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

            $this->logger->error('Error when updating shipping address of subscription ' . $subscriptionId . ': ' . $exception->getMessage());

            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorUpdateAddress'));
            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        }
    }

    /**
     * @LoginRequired()
     * @Route("/account/mollie/subscriptions/{subscriptionId}/payment/update", name="frontend.account.mollie.subscriptions.payment.update", methods={"POST"})
     *
     * @param string $subscriptionId
     * @param SalesChannelContext $salesChannelContext
     * @return Response
     */
    public function updatePaymentStart(string $subscriptionId, SalesChannelContext $salesChannelContext): Response
    {
        try {

            $checkoutUrl = $this->subscriptionManager->updatePaymentMethodStart($subscriptionId, $salesChannelContext->getContext());

            return $this->redirect($checkoutUrl);

        } catch (\Throwable $exception) {

            $this->logger->error('Error when updating payment method of subscription ' . $subscriptionId . ': ' . $exception->getMessage());

            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorUpdatePayment'));
            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        }
    }

    /**
     * @LoginRequired()
     * @Route("/account/mollie/subscriptions/{subscriptionId}/payment/update/finish", name="frontend.account.mollie.subscriptions.payment.update-success", methods={"GET", "POST"})
     *
     * @param string $subscriptionId
     * @param SalesChannelContext $salesChannelContext
     * @return Response
     */
    public function updatePaymentFinish(string $subscriptionId, SalesChannelContext $salesChannelContext): Response
    {
        try {

            $this->subscriptionManager->updatePaymentMethodConfirm($subscriptionId, $salesChannelContext->getContext());

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successUpdatePayment'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');

        } catch (\Throwable $exception) {

            $this->logger->error('Error when updating payment method of subscription ' . $subscriptionId . ': ' . $exception->getMessage());

            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorUpdatePayment'));
            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        }
    }

    /**
     * @LoginRequired()
     * @Route("/account/mollie/subscriptions/{subscriptionId}/cancel", name="frontend.account.mollie.subscriptions.cancel", methods={"POST"})
     */
    public function cancelSubscription($subscriptionId, SalesChannelContext $context): Response
    {
        try {

            $this->subscriptionManager->cancelSubscription($subscriptionId, $context->getContext());

            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.cancelSubscription'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');

        } catch (\Throwable $exception) {

            $this->logger->error('Error when canceling subscription ' . $subscriptionId . ': ' . $exception->getMessage());

            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorCancelSubscription'));
            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        }
    }

}
