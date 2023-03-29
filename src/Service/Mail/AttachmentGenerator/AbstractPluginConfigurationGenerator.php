<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;

abstract class AbstractPluginConfigurationGenerator extends AbstractSalesChannelGenerator
{
    /**
     * @var ConfigurationService
     */
    protected $configurationService;

    /**
     * @var SettingsService
     */
    protected $settingsService;

    /**
     * @param ConfigurationService $configurationService
     * @param SalesChannelRepositoryInterface $salesChannelRepository
     * @param SettingsService $settingsService
     */
    public function __construct(
        ConfigurationService            $configurationService,
        SalesChannelRepositoryInterface $salesChannelRepository,
        SettingsService                 $settingsService
    ) {
        parent::__construct($salesChannelRepository);

        $this->configurationService = $configurationService;
        $this->settingsService = $settingsService;
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
            $elements = array_merge($elements, $card['elements']);
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
