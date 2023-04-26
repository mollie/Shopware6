<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Order\Sw65;

use Kiener\MolliePayments\Controller\Api\Order\OrderControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=true, "auth_enabled"=true})
 */
class OrderController extends OrderControllerBase
{
}
