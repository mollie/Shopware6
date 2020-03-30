<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;

class SettingsService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config.';

    /** @var EntityRepositoryInterface $systemConfigRepository */
    protected $systemConfigRepository;

    public function __construct(EntityRepositoryInterface $systemConfigRepository)
    {
        $this->systemConfigRepository = $systemConfigRepository;
    }

    /**
     * Get Mollie settings from configuration.
     *
     * @param string       $salesChannelId
     * @param Context|null $context
     *
     * @return MollieSettingStruct
     * @throws InconsistentCriteriaIdsException
     */
    public function getSettings(string $salesChannelId, ?Context $context = null): MollieSettingStruct
    {
        $structData = [];

        /** @var SystemConfigCollection $settingsCollection */
        $settingsCollection = $this->getMollieConfigurationCollection($salesChannelId, $context);

        /** @var SystemConfigEntity $systemConfigEntity */
        foreach ($settingsCollection as $systemConfigEntity) {
            $configurationKey = $systemConfigEntity->getConfigurationKey();

            $identifier = (string)substr($configurationKey, \strlen(self::SYSTEM_CONFIG_DOMAIN));

            if ($identifier === '') {
                continue;
            }

            $structData[$identifier] = $systemConfigEntity->getConfigurationValue();
        }

        return (new MollieSettingStruct())->assign($structData);
    }

    /**
     * Get Mollie configuration collection.
     *
     * @param string       $salesChannelId
     * @param Context|null $context
     *
     * @return SystemConfigCollection
     * @throws InconsistentCriteriaIdsException
     */
    protected function getMollieConfigurationCollection(string $salesChannelId, ?Context $context = null): SystemConfigCollection
    {
        // Set default context
        if ($context === null) {
            $context = Context::createDefaultContext();
        }

        // Create filter criteria
        $criteria = (new Criteria())
            ->addFilter(new ContainsFilter('configurationKey', self::SYSTEM_CONFIG_DOMAIN))
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        /** @var SystemConfigCollection $systemConfigCollection */
        $systemConfigCollection = $this->systemConfigRepository
            ->search($criteria, $context)->getEntities();

        return $systemConfigCollection;
    }
}