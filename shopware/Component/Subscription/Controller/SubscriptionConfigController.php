<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Controller;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final class SubscriptionConfigController extends AbstractController
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
    ) {
    }

    #[Route(path: '/api/_action/mollie/config/subscription', name: 'api.action.mollie.config.subscription', methods: ['POST'])]
    public function subscriptionConfig(): JsonResponse
    {
        return new JsonResponse([
            'enabled' => $this->settingsService->getSubscriptionSettings()->isEnabled(),
        ]);
    }
}
