<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate;

use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Mandate\Route\AbstractListMandatesRoute;
use Mollie\Shopware\Component\Payment\Mandate\Route\ListMandatesRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class AccountMandatesController extends StorefrontController
{
    public function __construct(
        private AccountOverviewPageLoader $overviewPageLoader,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: ListMandatesRoute::class)]
        private AbstractListMandatesRoute $listMandatesRoute,
    ) {
    }

    #[Route(path: '/account/mollie/mandates', name: 'frontend.account.mollie.mandates.page', methods: ['GET'], options: ['seo' => false])]
    public function mandatesList(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            return new RedirectResponse($this->generateUrl('frontend.account.login'));
        }

        $page = $this->overviewPageLoader->load($request, $salesChannelContext, $customer);

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelContext->getSalesChannelId());
        $mandates = new MandateCollection();

        if ($paymentSettings->isOneClickPayment()) {
            try {
                $response = $this->listMandatesRoute->list($customer->getId(), $salesChannelContext);
                $mandates = $response->getMandates()->filterByPaymentMethod(PaymentMethod::CREDIT_CARD);
            } catch (\Throwable $e) {
                $mandates = new MandateCollection();
            }
        }

        return $this->renderStorefront(
            '@Storefront/storefront/page/account/mandates/index.html.twig',
            [
                'page' => $page,
                'enable_one_click_payments' => $paymentSettings->isOneClickPayment(),
                'MollieCreditCardMandateCollection' => $mandates,
            ]
        );
    }
}
