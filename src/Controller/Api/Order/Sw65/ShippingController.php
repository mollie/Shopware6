<?php

namespace Kiener\MolliePayments\Controller\Api\Order\Sw65;

use Kiener\MolliePayments\Controller\Api\Order\ShippingControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=true, "auth_enabled"=true})
 */
class ShippingController extends ShippingControllerBase
{
}
