<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Payment\Sw65;

use Kiener\MolliePayments\Controller\Api\Payment\ReturnControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=false, "auth_enabled"=false})
 */
class ReturnController extends ReturnControllerBase
{
}
