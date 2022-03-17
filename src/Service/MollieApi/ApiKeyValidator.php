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
     * @return bool
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function validate(string $key): bool
    {
        $apiClient = $this->apiFactory->buildClient($key);

        /** @var Profile $profile */
        $profile = $apiClient->profiles->getCurrent();

        return ($profile instanceof Profile && isset($profile->id));
    }
}
