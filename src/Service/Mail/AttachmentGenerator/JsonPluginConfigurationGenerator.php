<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Service\ConfigService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class JsonPluginConfigurationGenerator extends AbstractPluginConfigurationGenerator
{
    /**
     * @var string[]
     */
    protected $ignoreKeys = [
        'extensions',
        'liveApiKey',
        'testApiKey',
        'profileId',
        'liveProfileId',
        'testProfileId',
    ];

    /**
     * @param Context $context
     * @return array<mixed>
     */
    public function generate(Context $context): array
    {
        $configs = [];

        foreach ($this->getSalesChannels($context) as $salesChannel) {
            $vars = $this->settingsService->getSettings($salesChannel->getId())->getVars();

            foreach ($this->ignoreKeys as $ignoreKey) {
                unset($vars[$ignoreKey]);
            }

            $varsWithDomain = [];
            foreach ($vars as $key => $value) {
                $varsWithDomain[rtrim(ConfigService::SYSTEM_CONFIG_DOMAIN, '.') . '.' . $key] = $value;
            }

            $configs[] = [
                'label' => $salesChannel->getTranslation('name'),
                'config' => $varsWithDomain,
            ];
        }

        return [
            'content' => json_encode($configs),
            'fileName' => 'plugin_configuration.json',
            'mimeType' => 'application/json'
        ];
    }
}
