<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig;

use Exception;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions\MollieRefundConfigException;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Services\MollieRefundConfigService;
use Kiener\MolliePayments\Service\MollieApi\ApiKeyValidator;
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
     * @var ApiKeyValidator
     */
    protected $apiKeyValidator;
    /**
     * @var SettingsService
     */
    private $settings;

    /**
     * @var SnippetFinderInterface
     */
    private $snippetFinder;

    /**
     * @var MollieRefundConfigService
     */
    private $configMollieRefundService;

    public function __construct(
        SettingsService $settings,
        SnippetFinderInterface $snippetFinder,
        ApiKeyValidator $apiKeyValidator,
        MollieRefundConfigService $configMollieRefundService,
    ) {
        $this->settings = $settings;
        $this->snippetFinder = $snippetFinder;
        $this->apiKeyValidator = $apiKeyValidator;
        $this->configMollieRefundService = $configMollieRefundService;
    }

    public function testApiKeys(Request $request): JsonResponse
    {
        $liveApiKey = $request->get('liveApiKey');
        $testApiKey = $request->get('testApiKey');

        return $this->testApiKeysAction($liveApiKey, $testApiKey);
    }

    public function testApiKeys64(Request $request): JsonResponse
    {
        $liveApiKey = $request->get('liveApiKey');
        $testApiKey = $request->get('testApiKey');

        return $this->testApiKeysAction($liveApiKey, $testApiKey);
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

    /**
     * This route can be used to get the configuration for the refund manager from the plugin configuration.
     * Depending on these settings, the merchant might have configured a different behaviour
     * for fields, flows and actions.
     */
    public function getRefundManagerConfig(Request $request, Context $context): JsonResponse
    {
        // it's important to get the sales channel.
        // because different sales channels might have different configured behaviours for the
        // employees of the merchant.
        // so depending on the order, we grab the matching sales channel configuration.
        $salesChannelID = (string) $request->get('salesChannelId');
        $orderId = (string) $request->get('orderId');

        if (empty($salesChannelID)) {
            $config = $this->settings->getSettings('');
        } else {
            $config = $this->settings->getSettings($salesChannelID);
        }

        try {
            return $this->configMollieRefundService->createConfigControllerResponse($orderId, $config, $salesChannelID, $context);
        } catch (MollieRefundConfigException $exception) {
            return ConfigControllerResponse::createFromMollieSettingStruct($config);
        }
    }

    public function getRefundManagerConfigLegacy(Request $request, Context $context): JsonResponse
    {
        return $this->getRefundManagerConfig($request, $context);
    }

    public function getSubscriptionConfig(): JsonResponse
    {
        $config = $this->settings->getSettings();

        return new JsonResponse([
            'enabled' => $config->isSubscriptionsEnabled(),
        ]);
    }

    private function testApiKeysAction(string $liveApiKey, string $testApiKey): JsonResponse
    {
        $keys = [
            [
                'key' => $liveApiKey,
                'mode' => 'live',
            ],
            [
                'key' => $testApiKey,
                'mode' => 'test',
            ],
        ];

        $results = [];

        foreach ($keys as $key) {
            $result = [
                'key' => $key['key'],
                'mode' => $key['mode'],
                'valid' => false,
            ];

            try {
                $result['valid'] = $this->apiKeyValidator->validate($key['key']);
            } catch (\Exception $e) {
                // No need to handle this exception
            }

            $results[] = $result;
        }

        return new JsonResponse([
            'results' => $results,
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
