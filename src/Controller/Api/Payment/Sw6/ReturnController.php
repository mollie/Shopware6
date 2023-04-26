<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Payment\Sw6;

use Kiener\MolliePayments\Controller\Api\Payment\ReturnControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(defaults={"auth_required"=false, "auth_enabled"=false})
 */
class ReturnController extends ReturnControllerBase
{
}
