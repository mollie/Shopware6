<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FailureMode;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\AccountOrderController;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[AsDecorator(decorates: AccountOrderController::class)]
final class FailureModeOrderController extends StorefrontController
{
    public function __construct(
        #[AutowireDecorated]
        private AccountOrderController $accountOrderController,
        #[Autowire(service: OrderRoute::class)]
        private AbstractOrderRoute $orderRoute,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        private AccountEditOrderPageLoader $accountEditOrderPageLoader,
    ) {
    }

    public function editOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        $salesChannelId = $context->getSalesChannelId();

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);

        if ($paymentSettings->isShopwareFailedPayment()) {
            return $this->accountOrderController->editOrder($orderId, $request, $context);
        }

        try {
            $editOrderPage = $this->accountEditOrderPageLoader->load($request, $context);
            $paymentMethods = $editOrderPage->getPaymentMethods();
        } catch (OrderException $exception) {
            $criteria = new Criteria([$orderId]);
            $orderRouteResponse = $this->orderRoute->load($request,$context,$criteria);
            $order = $orderRouteResponse->getOrders()->first();

            if ($order instanceof OrderEntity) {
                $this->addFlash(self::DANGER, $this->trans('error.' . $exception->getErrorCode(), ['%orderNumber%' => (string) $order->getOrderNumber()]));
            }

            return $this->redirectToRoute('frontend.account.order.page');
        }

        $paymentMethodList = [];
        foreach ($paymentMethods as $paymentMethod) {
            /** @var ?PaymentMethod $paymentMethodExtension */
            $paymentMethodExtension = $paymentMethod->getExtension(Mollie::EXTENSION);
            if ($paymentMethodExtension === null) {
                continue;
            }
            $paymentMethodList[] = $paymentMethodExtension->getPaymentMethod()->value;
        }

        return $this->renderStorefront('@Storefront/storefront/page/checkout/payment/failed.html.twig', [
            'redirectUrl' => $this->generateUrl('frontend.account.edit-order.update-order', ['orderId' => $orderId]),
            'paymentMethods' => $paymentMethodList,
            'paymentMethodId' => $context->getPaymentMethod()->getId(),
        ]);
    }

    public function orderOverview(Request $request, SalesChannelContext $context): Response
    {
        return $this->accountOrderController->orderOverview($request, $context);
    }

    public function cancelOrder(Request $request, SalesChannelContext $context): Response
    {
        return $this->accountOrderController->cancelOrder($request, $context);
    }

    public function orderSingleOverview(Request $request, SalesChannelContext $context): Response
    {
        return $this->accountOrderController->orderSingleOverview($request, $context);
    }

    public function ajaxOrderDetail(Request $request, SalesChannelContext $context): Response
    {
        return $this->accountOrderController->ajaxOrderDetail($request, $context);
    }

    public function orderChangePayment(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        return $this->accountOrderController->orderChangePayment($orderId, $request, $context);
    }

    public function updateOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        return $this->accountOrderController->updateOrder($orderId, $request, $context);
    }
}
