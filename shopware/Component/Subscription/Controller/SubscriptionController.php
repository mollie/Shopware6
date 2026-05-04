<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Controller;

use Mollie\Shopware\Component\Subscription\Page\SubscriptionPageLoader;
use Mollie\Shopware\Component\Subscription\Route\AbstractUpdateAddressRoute;
use Mollie\Shopware\Component\Subscription\Route\AbstractUpdatePaymentMethodRoute;
use Mollie\Shopware\Component\Subscription\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Subscription\Route\UpdateAddressRoute;
use Mollie\Shopware\Component\Subscription\Route\UpdatePaymentMethodRoute;
use Mollie\Shopware\Component\Subscription\Route\WebhookRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandlerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID], 'csrf_protected' => false])]
final class SubscriptionController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: WebhookRoute::class)]
        private readonly AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: UpdateAddressRoute::class)]
        private readonly AbstractUpdateAddressRoute $updateAddressRoute,
        #[Autowire(service: UpdatePaymentMethodRoute::class)]
        private readonly AbstractUpdatePaymentMethodRoute $updatePaymentMethodRoute,
        private readonly SubscriptionPageLoader $pageLoader,
        #[Autowire(service: SubscriptionActionHandler::class)]
        private readonly SubscriptionActionHandlerInterface $actionHandler,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/account/mollie/subscriptions',
        name: 'frontend.account.mollie.subscriptions.page',
        options: ['seo' => false],
        defaults: ['_loginRequired' => true, 'XmlHttpRequest' => true],
        methods: ['GET', 'POST']
    )]
    public function subscriptionsList(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if ($salesChannelContext->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $page = $this->pageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront(
            '@Storefront/storefront/page/account/subscriptions/index.html.twig',
            ['page' => $page]
        );
    }

    #[Route(path: '/mollie/webhook/subscription/{subscriptionId}', name: 'frontend.mollie.webhook.subscription', options: ['seo' => false], methods: ['GET', 'POST'])]
    public function webhook(string $subscriptionId, Request $request, SalesChannelContext $context): Response
    {
        try {
            $this->logger->debug('Subscription Webhook received', [
                'transactionId' => $subscriptionId,
            ]);
            $response = $this->webhookRoute->notify($subscriptionId, $request,$context->getContext());

            return new JsonResponse($response->getObject());
        } catch (ShopwareHttpException $exception) {
            $this->logger->warning(
                'Subscription Webhook request failed with warning',
                [
                    'transactionId' => $subscriptionId,
                    'message' => $exception->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Subscription Webhook request failed',
                [
                    'transactionId' => $subscriptionId,
                    'message' => $exception->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], 422);
        }
    }

    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/pause', name: 'frontend.account.mollie.subscriptions.pause', defaults: ['action' => 'pause'],methods: ['POST'])]
    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/resume', name: 'frontend.account.mollie.subscriptions.resume', defaults: ['action' => 'resume'],methods: ['POST'])]
    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/skip', name: 'frontend.account.mollie.subscriptions.skip', defaults: ['action' => 'skip'],methods: ['POST'])]
    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/cancel', name: 'frontend.account.mollie.subscriptions.cancel', defaults: ['action' => 'cancel'],methods: ['POST'])]
    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/{action}', name: 'frontend.account.mollie.subscriptions.changeState', methods: ['POST'])]
    public function changeState(string $subscriptionId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if ($salesChannelContext->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $action = $request->attributes->get('action');

        $translationKey = 'molliePayments.subscriptions.account.%s%s';

        try {
            $this->actionHandler->handle($action, $subscriptionId, $salesChannelContext->getContext());
            $translationKey = sprintf($translationKey, 'success', ucfirst($action));
            $this->addFlash(self::SUCCESS, $this->trans($translationKey));
        } catch (\Throwable $exception) {
            $translationKey = sprintf($translationKey, 'error', ucfirst($action));
            $this->logger->error('Error when changing subscription state', [
                'subscriptionId' => $subscriptionId,
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);

            $this->addFlash(self::DANGER, $this->trans($translationKey));
        }

        return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
    }

    #[Route(
        path: '/account/mollie/subscriptions/{subscriptionId}/billing/update',
        name: 'frontend.account.mollie.subscriptions.billing.update',
        defaults: ['_loginRequired' => true],
        methods: ['POST']
    )]
    public function updateBillingAddress(string $subscriptionId, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        if ($salesChannelContext->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->updateAddressRoute->updateBilling($subscriptionId, $data, $salesChannelContext);
            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successUpdateAddress'));
        } catch (\Throwable $exception) {
            $this->logger->error('Error when updating billing address of subscription', [
                'subscriptionId' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorUpdateAddress'));
        }

        return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
    }

    #[Route(
        path: '/account/mollie/subscriptions/{subscriptionId}/shipping/update',
        name: 'frontend.account.mollie.subscriptions.shipping.update',
        defaults: ['_loginRequired' => true],
        methods: ['POST']
    )]
    public function updateShippingAddress(string $subscriptionId, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        if ($salesChannelContext->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->updateAddressRoute->updateShipping($subscriptionId, $data, $salesChannelContext);
            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successUpdateAddress'));
        } catch (\Throwable $exception) {
            $this->logger->error('Error when updating shipping address of subscription', [
                'subscriptionId' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorUpdateAddress'));
        }

        return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
    }

    #[Route(
        path: '/account/mollie/subscriptions/{subscriptionId}/payment/update',
        name: 'frontend.account.mollie.subscriptions.payment.update',
        defaults: ['_loginRequired' => true],
        methods: ['POST']
    )]
    public function updatePaymentStart(string $subscriptionId, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        if ($salesChannelContext->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $response = $this->updatePaymentMethodRoute->start($subscriptionId, $data, $salesChannelContext);
            $checkoutUrl = (string) $response->getObject()->get('checkoutUrl');

            return $this->redirect($checkoutUrl);
        } catch (\Throwable $exception) {
            $this->logger->error('Error when starting payment method update of subscription', [
                'subscriptionId' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorUpdatePayment'));

            return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
        }
    }

    #[Route(
        path: '/account/mollie/subscriptions/{subscriptionId}/payment/update/finish',
        name: 'frontend.account.mollie.subscriptions.payment.update-success',
        defaults: ['_loginRequired' => true],
        methods: ['GET', 'POST']
    )]
    public function updatePaymentFinish(string $subscriptionId, SalesChannelContext $salesChannelContext): Response
    {
        if ($salesChannelContext->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->updatePaymentMethodRoute->confirm($subscriptionId, $salesChannelContext);
            $this->addFlash(self::SUCCESS, $this->trans('molliePayments.subscriptions.account.successUpdatePayment'));
        } catch (\Throwable $exception) {
            $this->logger->error('Error when finishing payment method update of subscription', [
                'subscriptionId' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
            $this->addFlash(self::DANGER, $this->trans('molliePayments.subscriptions.account.errorUpdatePayment'));
        }

        return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
    }
}
