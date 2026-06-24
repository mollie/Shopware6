<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{profileId:string,testMode:bool,locale:string,oneClickPayments:bool}>>
 */
final class ConfigResponse extends StoreApiResponse
{
    public function __construct(string $profileId, bool $testMode, string $locale, bool $oneClickPayments)
    {
        parent::__construct(new ArrayStruct(
            [
                'profileId' => $profileId,
                'testMode' => $testMode,
                'locale' => $locale,
                'oneClickPayments' => $oneClickPayments,
            ],
            'mollie_payments_config'
        ));
    }
}
