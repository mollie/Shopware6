<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;

interface PluginSettingsServiceInterface
{

    /**
     * @return string
     */
    public function getEnvMollieShopDomain(): string;

    /**
     * @return bool
     */
    public function getEnvMollieDevMode(): bool;

}
