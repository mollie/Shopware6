<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\RefundCompositionBuilder;
use Mollie\Shopware\Component\Refund\RefundOrderLoader;
use Mollie\Shopware\Component\Refund\RefundTotalsBuilder;
use Mollie\Shopware\Component\Refund\Struct\CartStruct;
use Mollie\Shopware\Component\Refund\Struct\RefundOverviewStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
final class RefundOverviewRoute extends AbstractRefundOverviewRoute
{
    public function __construct(
        private readonly RefundOrderLoader $orderLoader,
        private readonly RefundCompositionBuilder $compositionBuilder,
        private readonly RefundTotalsBuilder $totalsBuilder,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractRefundOverviewRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/api/_action/mollie/order/refund-overview',
        name: 'api.action.mollie.order.refund-overview',
        methods: ['POST'],
    )]
    public function overview(Request $request, Context $context): JsonResponse
    {
        $orderId = (string) $request->get('orderId');

        $order = $this->orderLoader->load($orderId, $context);
        $orderNumber = (string) $order->getOrderNumber();

        $this->logger->info('Refund overview requested', [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
        ]);

        $struct = new RefundOverviewStruct();

        $payment = $this->orderLoader->findPayment($order, $context);

        if (! $payment instanceof Payment) {
            $this->logger->debug('No Mollie payment found for refund overview', [
                'orderId' => $orderId,
                'orderNumber' => $orderNumber,
            ]);

            return $this->json($struct);
        }

        $freshPayment = $this->orderLoader->loadFresh($payment, $order);
        $refunds = $this->compositionBuilder->enrichRefundsWithComposition($freshPayment->getRefunds(), $order);

        $cart = CartStruct::fromOrder($order);
        $cart->applyRefundedQuantities($this->compositionBuilder->buildRefundedQuantities($order, $refunds));
        $cart->applyRefundedAmounts($this->compositionBuilder->buildRefundedAmounts($order, $refunds));

        $struct->setCart($cart);
        $struct->setTotals($this->totalsBuilder->build($order, $payment, $freshPayment));
        $struct->setRefunds($refunds);

        return $this->json($struct);
    }
}
