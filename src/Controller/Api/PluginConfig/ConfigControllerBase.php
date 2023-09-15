<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig;

use Exception;
use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\MollieApi\ApiKeyValidator;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Profile;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
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

    /**
     * @var ApiKeyValidator
     */
    protected $apiKeyValidator;

    /**
     * @param SettingsService $settings
     * @param SnippetFinderInterface $snippetFinder
     * @param ApiKeyValidator $apiKeyValidator
     */
    public function __construct(SettingsService $settings, SnippetFinderInterface $snippetFinder, ApiKeyValidator $apiKeyValidator)
    {
        $this->settings = $settings;
        $this->snippetFinder = $snippetFinder;
        $this->apiKeyValidator = $apiKeyValidator;
    }

    /**
     * @Route("/api/v{version}/_action/mollie/config/test-api-keys", name="api.action.mollie.config.test-api-keys", methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testApiKeys(Request $request): JsonResponse
    {
        $liveApiKey = $request->get('liveApiKey');
        $testApiKey = $request->get('testApiKey');

        return $this->testApiKeysAction($liveApiKey, $testApiKey);
    }

    /**
     * @Route("/api/_action/mollie/config/test-api-keys", name="api.action.mollie.config.test-api-keys-64", methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
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
     * @Route("/api/_action/mollie/config/validate/flowbuilder", name="api.action.mollie.config.validate.flowbuilder", methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateFlowBuilder(Request $request, Context $context): JsonResponse
    {
        $locale = (string)$request->get('locale');

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
                    'warnings' => $warnings
                ],
            ],
        ]);
    }


    /**
     * @param string $liveApiKey
     * @param string $testApiKey
     * @return JsonResponse
     */
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
            ]
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
            } catch (Exception $e) {
                // No need to handle this exception
            }

            $results[] = $result;
        }

        return new JsonResponse([
            'results' => $results
        ]);
    }

    /**
     * This route can be used to get the configuration for the refund manager from the plugin configuration.
     * Depending on these settings, the merchant might have configured a different behaviour
     * for fields, flows and actions.
     *
     * @Route("/api/_action/mollie/config/refund-manager", name="api.action.mollie.config.refund-manager", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function getRefundManagerConfig(Request $request, Context $context): JsonResponse
    {
        // it's important to get the sales channel.
        // because different sales channels might have different configured behaviours for the
        // employees of the merchant.
        // so depending on the order, we grab the matching sales channel configuration.
        $salesChannelID = (string)$request->get('salesChannelId');

        if (empty($salesChannelID)) {
            $config = $this->settings->getSettings('');
        } else {
            $config = $this->settings->getSettings($salesChannelID);
        }

        return new JsonResponse([
            'enabled' => $config->isRefundManagerEnabled(),
            'autoStockReset' => $config->isRefundManagerAutoStockReset(),
            'verifyRefund' => $config->isRefundManagerVerifyRefund(),
            'showInstructions' => $config->isRefundManagerShowInstructions(),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/mollie/config/refund-manager", name="api.action.mollie.config.refund-manager.legacy", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function getRefundManagerConfigLegacy(Request $request, Context $context): JsonResponse
    {
        return $this->getRefundManagerConfig($request, $context);
    }

    /**
     * @param string $snippetName
     * @param string $locale
     * @return string
     */
    private function getAdminSnippet(string $snippetName, string $locale): string
    {
        $path = explode('.', $snippetName);

        $snippets = $this->snippetFinder->findSnippets($locale);

        foreach ($path as $elem) {
            $snippets = $snippets[$elem];

            if (!is_array($snippets)) {
                return $snippets;
            }
        }

        return '';
    }
}
