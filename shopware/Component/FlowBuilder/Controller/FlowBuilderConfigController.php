<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Controller;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
final class FlowBuilderConfigController extends AbstractController
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: SnippetFinder::class)]
        private readonly SnippetFinderInterface $snippetFinder,
    ) {
    }

    #[Route(
        path: '/api/_action/mollie/config/validate/flowbuilder',
        name: 'api.action.mollie.config.validate.flowbuilder',
        methods: ['POST'],
    )]
    public function validateFlowBuilder(Request $request, Context $context): JsonResponse
    {
        $locale = (string) $request->get('locale');
        if ($locale === '') {
            $locale = 'en-GB';
        }
        /**
         * @var string[] $salesChannelIds
         */
        $salesChannelIds = $this->salesChannelRepository->searchIds(new Criteria(), $context)->getIds();

        $automaticShippingFound = false;
        foreach ($salesChannelIds as $salesChannelId) {
            $paymentSettings = $this->settingsService->getPaymentSettings((string) $salesChannelId);
            if ($paymentSettings->isAutomaticShipment()) {
                $automaticShippingFound = true;
                break;
            }
        }

        $warnings = [];
        if ($automaticShippingFound) {
            $warnings[] = $this->getAdminSnippet('mollie-payments.sw-flow.actions.warnings.automaticShipping', $locale);
        }

        return new JsonResponse([
            'locale' => $locale,
            'actions' => [
                'shipping' => [
                    'warnings' => $warnings,
                ],
            ],
        ]);
    }

    private function getAdminSnippet(string $snippetName, string $locale): string
    {
        $path = explode('.', $snippetName);
        $snippets = $this->snippetFinder->findSnippets($locale);

        foreach ($path as $elem) {
            $snippets = $snippets[$elem];
            if (! is_array($snippets)) {
                return $snippets;
            }
        }

        return '';
    }
}
