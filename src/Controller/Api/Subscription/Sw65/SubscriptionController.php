<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Subscription\Sw65;

use Kiener\MolliePayments\Controller\Api\Subscription\SubscriptionControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=true, "auth_enabled"=true})
 */
class SubscriptionController extends SubscriptionControllerBase
{
}
