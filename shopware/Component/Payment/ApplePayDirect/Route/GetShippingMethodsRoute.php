<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayShippingMethod;
use Mollie\Shopware\Component\Payment\ApplePayDirect\FakeApplePayAddress;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class GetShippingMethodsRoute extends AbstractGetShippingMethodsRoute
{
    /**
     * @param EntityRepository<CustomerAddressCollection<CustomerEntity>> $customerAddressRepository
     */
    public function __construct(
        #[Autowire(service: CheckoutGatewayRoute::class)]
        private AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        #[Autowire(service: SetShippingMethodRoute::class)]
        private AbstractSetShippingMethodRoute $setShippingMethodRoute,
        #[Autowire(service: GetCartRoute::class)]
        private AbstractGetCartRoute $getCartRoute,
        #[Autowire(service: 'customer_address.repository')]
        private EntityRepository $customerAddressRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractGetShippingMethodsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.apple-pay.shipping-methods', path: '/store-api/mollie/applepay/shipping-methods', methods: ['POST'])]
    public function methods(Request $request, Cart $cart, SalesChannelContext $salesChannelContext): GetShippingMethodsResponse
    {
        $request->query->set('onlyAvailable', '1');
        $checkoutResponse = $this->checkoutGatewayRoute->load($request, $cart, $salesChannelContext);

        $selectedShippingMethodId = $salesChannelContext->getShippingMethod()->getId();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $logData = [
            'shippingMethodId' => $selectedShippingMethodId,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->info('Start - get shipping methods for apple pay express', $logData);

        $applePayMethods = [];
        $shippingMethods = $checkoutResponse->getShippingMethods();

        /** @var ShippingMethodEntity $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $detail = '';
            $shippingMethodId = $shippingMethod->getId();
            $deliveryTime = $shippingMethod->getDeliveryTime();
            if ($deliveryTime instanceof DeliveryTimeEntity) {
                $detail = (string) $deliveryTime->getName();
            }
            $tempContext = $this->setShippingMethod($shippingMethodId, $salesChannelContext);
            $cartResponse = $this->getCartRoute->cart($request, $tempContext);

            $cart = $cartResponse->getCart();
            $shippingCosts = $cart->getShippingAmount();

            $applePayMethods[$shippingMethodId] = new ApplePayShippingMethod($shippingMethod->getId(), (string) $shippingMethod->getName(), $detail, $shippingCosts);
        }

        $this->setShippingMethod($selectedShippingMethodId, $salesChannelContext);

        $applePayMethods = $this->setSelectedMethodToFirstElement($applePayMethods, $selectedShippingMethodId);

        $customer = $salesChannelContext->getCustomer();
        if ($customer !== null) {
            $fakeAddressId = FakeApplePayAddress::getId($customer);
            $this->customerAddressRepository->delete([
                [
                    'id' => $fakeAddressId,
                ]
            ], $salesChannelContext->getContext());
        }

        $this->logger->info('Finished - get shipping methods for apple pay express', $logData);

        return new GetShippingMethodsResponse($applePayMethods);
    }

    private function setShippingMethod(string $shippingMethodId, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $request = new Request();
        $request->attributes->set('identifier', $shippingMethodId);
        $setShippingMethodResponse = $this->setShippingMethodRoute->setShipping($request, $salesChannelContext);

        return $setShippingMethodResponse->getSalesChannelContext();
    }

    /**
     * @param ApplePayShippingMethod[] $applePayMethods
     *
     * @return ApplePayShippingMethod[]
     */
    private function setSelectedMethodToFirstElement(array $applePayMethods, string $selectedShippingMethodId): array
    {
        $selectedShippingMethod = $applePayMethods[$selectedShippingMethodId];

        unset($applePayMethods[$selectedShippingMethodId]);
        $applePayMethods = array_values($applePayMethods);
        array_unshift($applePayMethods, $selectedShippingMethod);

        return $applePayMethods;
    }
}
