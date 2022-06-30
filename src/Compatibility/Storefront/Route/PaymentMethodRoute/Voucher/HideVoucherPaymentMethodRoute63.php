<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Voucher;

use Kiener\MolliePayments\Service\Cart\Voucher\VoucherCartCollector;
use Kiener\MolliePayments\Service\Cart\Voucher\VoucherService;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemoverInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;


class HideVoucherPaymentMethodRoute63 extends AbstractPaymentMethodRoute
{

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $corePaymentMethodRoute;

    /**
     * @var PaymentMethodRemoverInterface
     */
    private $paymentMethodRemover;

    /**
     * @param AbstractPaymentMethodRoute $corePaymentMethodRoute
     * @param ContainerInterface $container
     * @param VoucherService $voucherService
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, PaymentMethodRemoverInterface $paymentMethodRemover)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;
        $this->paymentMethodRemover = $paymentMethodRemover;
    }

    /**
     * @return AbstractPaymentMethodRoute
     */
    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->corePaymentMethodRoute;
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     * @return PaymentMethodRouteResponse
     */
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context);

        return $this->paymentMethodRemover->removePaymentMethods($originalData, $context);
    }

    /**
     * We have to use lazy loading for this. Otherwise there are plugin compatibilities
     * with a circular reference...even though XML looks fine.
     *
     * @return CartService
     * @throws \Exception
     */
    private function getCartServiceLazy(): CartService
    {
        $service = $this->container->get('Shopware\Core\Checkout\Cart\SalesChannel\CartService');

        if (!$service instanceof CartService) {
            throw new \Exception('CartService of Shopware not found!');
        }

        return $service;
    }

}
