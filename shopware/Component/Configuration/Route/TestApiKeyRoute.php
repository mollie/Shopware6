<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Configuration\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Mode;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final class TestApiKeyRoute
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
    ) {
    }

    #[Route(
        path: '/api/_action/mollie/config/test-api-keys',
        name: 'api.action.mollie.config.test-api-keys',
        methods: ['POST']
    )]
    public function testApiKeys(Request $request): JsonResponse
    {
        $results = [];

        foreach (Mode::cases() as $mode) {
            $apiKey = (string) $request->get($mode->value . 'ApiKey', '');
            $valid = false;

            try {
                $this->mollieGateway->getProfileForApiKey($apiKey);
                $valid = true;
            } catch (\Throwable $e) {
            }

            $results[] = [
                'key' => $apiKey,
                'mode' => $mode->value,
                'valid' => $valid,
            ];
        }

        return new JsonResponse(['results' => $results]);
    }
}
