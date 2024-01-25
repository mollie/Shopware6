<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Config\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class ConfigResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<mixed, mixed>
     */
    protected $object;


    /**
     * @param string $profileId
     * @param bool $isTestMode
     * @param string $defaultLocale
     * @param bool $oneClickEnabled
     */
    public function __construct(string $profileId, bool $isTestMode, string $defaultLocale, bool $oneClickEnabled)
    {
        $this->object = new ArrayStruct(
            [
                'profileId' => $profileId,
                'testMode' => $isTestMode,
                'locale' => $defaultLocale,
                'oneClickPayments' => $oneClickEnabled,
            ],
            'mollie_payments_config'
        );

        parent::__construct($this->object);
    }
}
