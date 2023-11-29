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
     */
    public function __construct(string $profileId, bool $isTestMode, string $defaultLocale)
    {
        $this->object = new ArrayStruct(
            [
                'profileId' => $profileId,
                'testMode' => $isTestMode,
                'locale' => $defaultLocale,
            ],
            'mollie_payments_config'
        );

        parent::__construct($this->object);
    }
}
