<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

final class WebhookRouteResponse extends StoreApiResponse
{
    public function __construct()
    {
        parent::__construct(new ArrayStruct(
            [
                'status' => 'success',
            ], 'webhook.route.success'
        ));
    }
}
