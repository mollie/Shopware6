<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayAmount;
use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayShippingMethod;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class GetShippingMethodsRoute extends AbstractGetShippingMethodsRoute
{
    public function __construct(
        #[Autowire(service: CheckoutGatewayRoute::class)]
        private AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
    ) {
    }

    public function getDecorated(): AbstractGetShippingMethodsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.apple-pay.shipping-methods', path: '/store-api/mollie/applepay/shipping-methods', methods: ['POST'])]
    public function methods(Request $request, Cart $cart, SalesChannelContext $context): GetShippingMethodsResponse
    {
        $request->query->set('onlyAvailable', '1');
        $checkoutResponse = $this->checkoutGatewayRoute->load($request, $cart, $context);

        $applePayMethods = [];
        $shippingMethods = $checkoutResponse->getShippingMethods();
        /** @var ShippingMethodEntity $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $detail = '';
            $deliveryTime = $shippingMethod->getDeliveryTime();
            if ($deliveryTime instanceof DeliveryTimeEntity) {
                $detail = (string) $deliveryTime->getName();
            }

            $applePayMethods[] = new ApplePayShippingMethod($shippingMethod->getId(), (string) $shippingMethod->getName(), $detail, new ApplePayAmount(0.0));
        }

        return new GetShippingMethodsResponse($applePayMethods);
    }
}
