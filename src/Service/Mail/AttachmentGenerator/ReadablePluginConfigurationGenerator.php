<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Service\MollieApi\ApiKeyValidator;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;

class ReadablePluginConfigurationGenerator extends AbstractPluginConfigurationGenerator
{
    /**
     * @var ApiKeyValidator
     */
    protected $apiKeyValidator;

    /**
     * @var string[]
     */
    protected $ignoreKeys = [
        'extensions',
        'profileId',
        'liveProfileId',
        'testProfileId',
    ];

    /**
     * @param EntityRepository<EntityCollection<SalesChannelEntity>> $salesChannelRepository
     */
    public function __construct(
        ConfigurationService $configurationService,
        $salesChannelRepository,
        SettingsService $settingsService,
        ApiKeyValidator $apiKeyValidator
    ) {
        $this->apiKeyValidator = $apiKeyValidator;

        parent::__construct($configurationService, $salesChannelRepository, $settingsService);
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     *
     * @return array<mixed>
     */
    public function generate(Context $context): array
    {
        $defaults = $this->getConfigurationDefaultValues($context);
        $translations = $this->getConfigurationTranslations($context);

        $fileContent = [];

        foreach ($this->getPluginConfiguration($context) as $config) {
            $fileContent[] = '[ ' . $config['label'] . ' ]';

            foreach ($config['config'] as $key => $value) {
                if (! empty($value) && in_array($key, ['liveApiKey', 'testApiKey'])) {
                    $value = $this->apiKeyValidator->validate($value) ? 'Valid' : 'Invalid';
                }

                $isDefaultValue = array_key_exists($key, $defaults) && $value === $defaults[$key];

                if (is_bool($value)) {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Disabled';
                }

                if (empty($value)) {
                    $value = 'Empty';
                }

                if ($isDefaultValue) {
                    $value .= ' (Default)';
                }

                $label = array_key_exists($key, $translations) ? $translations[$key] : $key;

                $fileContent[] = $label . ': ' . $value;
            }

            $fileContent[] = '';
        }

        return [
            'content' => implode("\r\n", $fileContent),
            'fileName' => 'plugin_configuration.txt',
            'mimeType' => 'text/plain',
        ];
    }

    /**
     * @return array<mixed>
     */
    protected function getPluginConfiguration(Context $context): array
    {
        $global = $this->settingsService->getSettings(null)->getVars();

        foreach ($this->ignoreKeys as $ignoreKey) {
            unset($global[$ignoreKey]);
        }

        $configs = [
            [
                'label' => 'Global settings',
                'config' => $global,
            ],
        ];

        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->getSalesChannels($context) as $salesChannel) {
            $vars = $this->settingsService->getSettings($salesChannel->getId())->getVars();

            foreach ($this->ignoreKeys as $ignoreKey) {
                unset($vars[$ignoreKey]);
            }

            $configs[] = [
                'label' => $salesChannel->getTranslation('name'),
                'config' => $vars,
            ];
        }

        return $configs;
    }
}
