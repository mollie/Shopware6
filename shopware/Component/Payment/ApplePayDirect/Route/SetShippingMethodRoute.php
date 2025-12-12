<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class SetShippingMethodRoute extends AbstractSetShippingMethodRoute
{
    public function __construct(
        #[Autowire(service: ContextSwitchRoute::class)]
        private AbstractContextSwitchRoute $contextSwitchRoute,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractSetShippingMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.apple-pay.set-shipping-methods', path: '/store-api/mollie/applepay/shipping-method', methods: ['POST'])]
    public function setShipping(Request $request, SalesChannelContext $salesChannelContext): SetShippingMethodResponse
    {
        $shippingMethodId = $request->get('identifier');
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $logData = [
            'shippingMethodId' => $shippingMethodId,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->info('Start - set apple pay shipping method', $logData);
        if ($shippingMethodId === null) {
            $this->logger->error('Shipping method id not set', $logData);
            throw ApplePayDirectException::missingShippingMethodIdentifier();
        }

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(SalesChannelContextService::SHIPPING_METHOD_ID, $shippingMethodId);
        $contextSwitchResponse = $this->contextSwitchRoute->switchContext($requestDataBag, $salesChannelContext);

        $salesChannelContextServiceParameters = new SalesChannelContextServiceParameters(
            $salesChannelContext->getSalesChannelId(),
            $contextSwitchResponse->getToken(),
            originalContext: $salesChannelContext->getContext(),
            customerId: $salesChannelContext->getCustomerId()
        );
        $newContext = $this->salesChannelContextService->get($salesChannelContextServiceParameters);
        $this->logger->info('Finsihed - set apple pay shipping method', $logData);

        return new SetShippingMethodResponse($newContext);
    }
}
