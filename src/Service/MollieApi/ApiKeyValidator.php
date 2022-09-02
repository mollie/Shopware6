<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Resources\Profile;

class ApiKeyValidator
{
    /**
     * @var MollieApiFactory
     */
    protected $apiFactory;

    public function __construct(MollieApiFactory $apiFactory)
    {
        $this->apiFactory = $apiFactory;
    }

    /**
     * @param string $key
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return bool
     */
    public function validate(string $key): bool
    {
        $apiClient = $this->apiFactory->buildClient($key);

        /** @var Profile $profile */
        $profile = $apiClient->profiles->getCurrent();

        if (!$profile instanceof Profile) {
            return false;
        }

        return !empty($profile->id);
    }
}
