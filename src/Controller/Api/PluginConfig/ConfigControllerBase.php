<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig;

use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigControllerBase extends AbstractController
{
    /**
     * @var SettingsService
     */
    private $settings;

    /**
     * @var SnippetFinderInterface
     */
    private $snippetFinder;

    public function __construct(
        SettingsService $settings,
        SnippetFinderInterface $snippetFinder
    ) {
        $this->settings = $settings;
        $this->snippetFinder = $snippetFinder;
    }

    /**
     * This route can be used to verify if there might be any warnings when using the flow builder.
     * Some automation settings might interfere with the flow builder and thus we try to
     * at least let the merchant know about it.
     */
    public function validateFlowBuilder(Request $request, Context $context): JsonResponse
    {
        $locale = (string) $request->get('locale');

        if (empty($locale)) {
            $locale = 'en-GB';
        }

        $automaticShippingFound = false;

        $allConfigs = $this->settings->getAllSalesChannelSettings($context);

        /**
         * @var string $scID
         * @var MollieSettingStruct $scConfig
         */
        foreach ($allConfigs as $scID => $scConfig) {
            if ($scConfig->getAutomaticShipping()) {
                $automaticShippingFound = true;
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
