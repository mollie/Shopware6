<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Support\Sw65;

use Kiener\MolliePayments\Controller\Api\Support\SupportControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_enabled"=true})
 */
class SupportController extends SupportControllerBase
{
}
