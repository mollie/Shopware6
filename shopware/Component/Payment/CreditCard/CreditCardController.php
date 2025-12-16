<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\CreditCard;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class CreditCardController extends StorefrontController
{
    public function __construct(
        private StoreCreditCardTokenRoute $storeCreditCardTokenRoute,
    ) {
    }

    #[Route(name: 'frontend.mollie.components.storeCardToken', path: '/mollie/components/store-card-token/{customerId}/{cardToken}', methods: ['GET'], options: ['seo' => false])]
    public function storeCardToken(string $customerId, string $cardToken, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $response = $this->storeCreditCardTokenRoute->store($customerId, $cardToken, $salesChannelContext->getContext());

        return new JsonResponse([
            'success' => true,
            'customerId' => $customerId,
            'result' => null,
        ]);
    }
}
