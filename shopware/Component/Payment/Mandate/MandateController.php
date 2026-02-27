<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate;

use Mollie\Shopware\Component\Payment\Mandate\Route\AbstractRevokeMandateRoute;
use Mollie\Shopware\Component\Payment\Mandate\Route\RevokeMandateRoute;
use Mollie\Shopware\Component\Payment\Mandate\Route\StoreMandateIdRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class MandateController extends StorefrontController
{
    public function __construct(
        private StoreMandateIdRoute $storeMandateIdRoute,
        #[Autowire(service: RevokeMandateRoute::class)]
        private AbstractRevokeMandateRoute $revokeMandateRoute,
    ) {
    }

    #[Route(name: 'frontend.mollie.components.storeMandateId', path: '/mollie/components/store-mandate-id/{customerId}/{mandateId}', methods: ['GET'], options: ['seo' => false])]
    public function storeId(string $customerId, string $mandateId, SalesChannelContext $salesChannelContext): Response
    {
        $this->storeMandateIdRoute->store($customerId, $mandateId, $salesChannelContext);

        return new JsonResponse([
            'success' => true,
            'customerId' => $customerId,
            'result' => null,
        ]);
    }

    #[Route(name: 'frontend.mollie.components.revokeMandate', path: '/mollie/components/revoke-mandate/{customerId}/{mandateId}', methods: ['GET'], options: ['seo' => false])]
    public function revoke(string $customerId, string $mandateId, SalesChannelContext $salesChannelContext): Response
    {
        $response = $this->revokeMandateRoute->revoke($customerId, $mandateId, $salesChannelContext);

        return new JsonResponse(
            [
                'success' => $response->isSuccess(),
                'customerId' => $customerId,
                'mandateId' => $mandateId,
                'result' => null,
            ]
        );
    }
}
