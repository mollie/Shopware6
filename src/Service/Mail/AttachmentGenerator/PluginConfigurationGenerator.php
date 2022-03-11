<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class PluginConfigurationGenerator implements GeneratorInterface
{
    protected $settingsService;

    /**
     * @param SettingsService $settingsService
     * @param EntityRepositoryInterface $salesChannelRepository
     */
    public function __construct(
        SettingsService $settingsService,
        EntityRepositoryInterface $salesChannelRepository
    )
    {
        $this->settingsService = $settingsService;
    }

    /**
     * @inheritDoc
     */
    public function generate(Context $context): array
    {
        $settings = $this->settingsService->getAllSalesChannelSettings($context);

        dd($settings);
    }
}
