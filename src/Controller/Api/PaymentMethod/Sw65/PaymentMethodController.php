<?php

namespace Kiener\MolliePayments\Controller\Api\PaymentMethod\Sw65;

use Kiener\MolliePayments\Controller\Api\PaymentMethod\PaymentMethodControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=true, "auth_enabled"=true})
 */
class PaymentMethodController extends PaymentMethodControllerBase
{
}
