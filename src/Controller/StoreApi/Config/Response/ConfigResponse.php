<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\Config\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{profileId:string,testMode:bool,locale:string,oneClickPayments:bool}>>
 */
class ConfigResponse extends StoreApiResponse
{
    public function __construct(string $profileId, bool $isTestMode, string $defaultLocale, bool $oneClickEnabled)
    {
        $object = new ArrayStruct(
            [
                'profileId' => $profileId,
                'testMode' => $isTestMode,
                'locale' => $defaultLocale,
                'oneClickPayments' => $oneClickEnabled,
            ],
            'mollie_payments_config'
        );

        parent::__construct($object);
    }
}
