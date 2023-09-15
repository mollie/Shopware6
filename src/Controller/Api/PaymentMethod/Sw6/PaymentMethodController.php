<?php

namespace Kiener\MolliePayments\Controller\Api\PaymentMethod\Sw6;

use Kiener\MolliePayments\Controller\Api\PaymentMethod\PaymentMethodControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(defaults={"auth_required"=true, "auth_enabled"=true})
 */
class PaymentMethodController extends PaymentMethodControllerBase
{
}
