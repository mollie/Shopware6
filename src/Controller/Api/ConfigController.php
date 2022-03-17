<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Exception;
use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Profile;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends AbstractController
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
     * @param SettingsService $settings
     * @param SnippetFinderInterface $snippetFinder
     */
    public function __construct(SettingsService $settings, SnippetFinderInterface $snippetFinder)
    {
        $this->settings = $settings;
        $this->snippetFinder = $snippetFinder;
    }


    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/config/test-api-keys", defaults={"auth_enabled"=true}, name="api.action.mollie.config.test-api-keys", methods={"POST"})
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
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/config/test-api-keys", defaults={"auth_enabled"=true}, name="api.action.mollie.config.test-api-keys-64", methods={"POST"})
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
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/config/validate/flowbuilder",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.config.validate.flowbuilder", methods={"POST"})
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
        /** @var array $keys */
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

        /** @var array $results */
        $results = [];

        foreach ($keys as $key) {
            $result = [
                'key' => $key['key'],
                'mode' => $key['mode'],
                'valid' => false,
            ];

            try {
                /** @var MollieApiClient $apiClient */
                $apiClient = new MollieApiClient();

                // Set the current API key
                $apiClient->setApiKey($key['key']);

                /** @var Profile $profile */
                $profile = $apiClient->profiles->getCurrent();

                // Check if the profile exists
                if (isset($profile->id)) {
                    $result['valid'] = true;
                }
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
