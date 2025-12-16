<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class AddProductRoute
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(name: 'store-api.mollie.apple-pay.add-product',path: '/store-api/mollie/applepay/add-product', methods: ['POST'])]
    public function addProduct(SalesChannelContext $salesChannelContext): AddProductResponse
    {
        $this->logger->warning('Deprecated add product route for apple pay was called. Please use shopware default addCartItemRoute with "isExpressCheckout" in request body');

        return new AddProductResponse();
    }
}
