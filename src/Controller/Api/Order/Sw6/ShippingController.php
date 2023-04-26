<?php

namespace Kiener\MolliePayments\Controller\Api\Order\Sw6;

use Kiener\MolliePayments\Controller\Api\Order\ShippingControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(defaults={"auth_required"=true, "auth_enabled"=true})
 */
class ShippingController extends ShippingControllerBase
{
}
