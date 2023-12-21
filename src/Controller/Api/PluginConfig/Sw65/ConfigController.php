<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Sw65;

use Kiener\MolliePayments\Controller\Api\PluginConfig\ConfigControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=true, "auth_enabled"=true})
 */
class ConfigController extends ConfigControllerBase
{
}
