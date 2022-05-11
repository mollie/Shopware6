<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Components\Subscription\Page\Account\SubscriptionPageLoader;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Page\Account\Mollie\AccountSubscriptionsPageLoader;
use Kiener\MolliePayments\Service\Subscription\CancelSubscriptionsService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * @param SubscriptionPageLoader $pageLoader
     * @param SubscriptionManager $subscriptionManager
     */
    public function __construct(SubscriptionPageLoader $pageLoader, SubscriptionManager $subscriptionManager)
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
     * @Route("/account/mollie/subscriptions/{subscriptionId}/billing/update", name="frontend.account.mollie.subscriptions.billing.update", methods={"POST"})
     *
     * @param string $subscriptionId
     * @param RequestDataBag $data
     * @param Context $context
     * @return Response
     */
    public function updateBilling(string $subscriptionId, RequestDataBag $data, Context $context): Response
    {
        $address = $data->get('address', null);

        if ($address === null) {
            #   $this->addFlash(self::SUCCESS,
            #   $this->trans('molliePayments.subscriptions.account.cancelSubscription',
            #        ['%1%' => $subscriptionId]
            #     )
            #  );
            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        }

        $firstName = $address['firstName'];
        $lastName = $address['lastName'];
        $company = $address['company'];
        $department = $address['department'];
        $street = $address['street'];
        $zipcode = $address['zipcode'];
        $city = $address['city'];


        $this->subscriptionManager->updateBillingAddress(
            $subscriptionId,
            $street,
            $zipcode,
            $city
        );

        return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
    }

    /**
     * @LoginRequired()
     * @Route("/account/mollie/subscriptions/{subscriptionId}/shipping/update", name="frontend.account.mollie.subscriptions.shipping.update", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function updateShipping(RequestDataBag $data, Context $context): JsonResponse
    {
        $this->subscriptionManager->updateShippingAddress();

        return new JsonResponse(['success' => true]);
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
