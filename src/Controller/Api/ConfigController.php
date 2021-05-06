<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Exception;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Profile;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends AbstractController
{
    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/config/test-api-keys",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.config.test-api-keys", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function testApiKeys(Request $request): JsonResponse
    {
        // Get the live API key
        $liveApiKey = $request->get('liveApiKey');

        // Get the test API key
        $testApiKey = $request->get('testApiKey');

        return $this->getResponse($liveApiKey, $testApiKey);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/config/test-api-keys",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.config.test-api-keys-64", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function testApiKeys64(Request $request): JsonResponse
    {
        // Get the live API key
        $liveApiKey = $request->get('liveApiKey');

        // Get the test API key
        $testApiKey = $request->get('testApiKey');

        return $this->getResponse($liveApiKey, $testApiKey);
    }

    private function getResponse(string $liveApiKey, string $testApiKey): JsonResponse
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
}
