<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Sw6;

use Exception;
use Kiener\MolliePayments\Controller\Api\PluginConfig\ConfigControllerBase;
use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\MollieApi\ApiKeyValidator;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Profile;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(defaults={"auth_required"=true, "auth_enabled"=true})
 */
class ConfigController extends ConfigControllerBase
{
}
