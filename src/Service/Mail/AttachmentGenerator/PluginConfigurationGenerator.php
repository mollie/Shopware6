<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;

abstract class PluginConfigurationGenerator implements GeneratorInterface
{
    /**
     * @var ConfigurationService
     */
    protected $configurationService;

    /**
     * @var EntityRepositoryInterface
     */
    protected $salesChannelRepository;

    /**
     * @var SettingsService
     */
    protected $settingsService;

    /**
     * @param ConfigurationService $configurationService
     * @param EntityRepositoryInterface $salesChannelRepository
     * @param SettingsService $settingsService
     */
    public function __construct(
        ConfigurationService      $configurationService,
        EntityRepositoryInterface $salesChannelRepository,
        SettingsService           $settingsService
    )
    {
        $this->configurationService = $configurationService;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->settingsService = $settingsService;
    }

    /**
     * @param Context $context
     * @return array<string, string>
     */
    protected function getSalesChannelIds(Context $context): array
    {
        /** @var SalesChannelEntity $salesChannel */
        return $this->getSalesChannels($context)->map(function ($salesChannel) {
            return $salesChannel->getId();
        });
    }

    /**
     * @param Context $context
     * @return SalesChannelCollection
     */
    protected function getSalesChannels(Context $context): SalesChannelCollection
    {
        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();
        return $salesChannels;
    }

    /**
     * @param Context $context
     * @return array<string, mixed>
     */
    protected function getConfigurationElements(Context $context): array
    {
        $elements = [];

        $cards = $this->configurationService->getConfiguration(
            rtrim(SettingsService::SYSTEM_CONFIG_DOMAIN, '.'),
            $context
        );

        foreach ($cards as $card) {
            $elements = [...$elements, ...$card['elements']];
        }

        return $elements;
    }

    /**
     * @param Context $context
     * @return array<string, mixed>
     */
    protected function getConfigurationTranslations(Context $context): array
    {
        $locale = 'en-GB'; // TODO figure out language from context.
        $translations = [];

        foreach ($this->getConfigurationElements($context) as $element) {
            if (array_key_exists('config', $element) &&
                array_key_exists('label', $element['config'])) {
                $name = substr($element['name'], strlen(SettingsService::SYSTEM_CONFIG_DOMAIN));
                $translations[$name] = $element['config']['label'][$locale];
            }
        }

        return $translations;
    }

    /**
     * @param Context $context
     * @return array<string, mixed>
     */
    protected function getConfigurationDefaultValues(Context $context): array
    {
        $defaults = [];

        foreach ($this->getConfigurationElements($context) as $element) {
            if (!array_key_exists('config', $element)) {
                continue;
            }

            $name = substr($element['name'], strlen(SettingsService::SYSTEM_CONFIG_DOMAIN));
            $defaultValue = null;

            if (array_key_exists('defaultValue', $element['config'])) {
                $defaultValue = $element['config']['defaultValue'];
            }

            if (array_key_exists('type', $element)) {
                switch ($element['type']) {
                    case 'bool':
                        $defaultValue = filter_var($defaultValue, FILTER_VALIDATE_BOOL);
                        break;
                }
            }

            $defaults[$name] = $defaultValue;
        }

        return $defaults;
    }
}
