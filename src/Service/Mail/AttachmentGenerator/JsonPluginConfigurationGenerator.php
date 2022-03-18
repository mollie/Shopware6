<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class JsonPluginConfigurationGenerator extends PluginConfigurationGenerator
{
    protected $ignoreKeys = [
        'extensions',
        'liveApiKey',
        'testApiKey',
        'profileId',
        'liveProfileId',
        'testProfileId',
    ];

    /**
     * @inheritDoc
     */
    public function generate(Context $context): array
    {
        $configs = [];

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

        return [
            'content' => json_encode($configs),
            'fileName' => 'plugin configuration.json',
            'mimeType' => 'application/json'
        ];
    }
}
