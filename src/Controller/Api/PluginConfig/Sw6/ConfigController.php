<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Sw6;

use Kiener\MolliePayments\Controller\Api\PluginConfig\ConfigControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(defaults={"auth_required"=true, "auth_enabled"=true})
 */
class ConfigController extends ConfigControllerBase
{
}
