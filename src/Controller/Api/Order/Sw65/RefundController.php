<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Order\Sw65;

use Kiener\MolliePayments\Controller\Api\Order\RefundControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=true, "auth_enabled"=true})
 */
class RefundController extends RefundControllerBase
{
}
