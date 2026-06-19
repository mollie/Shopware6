<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Attachment\Generator;

use Mollie\Shopware\Component\Mollie\Gateway\ClientFactoryInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Support\Attachment\Attachment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ReadableConfigGenerator implements AttachmentGeneratorInterface
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        private readonly ClientFactoryInterface $clientFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(Context $context): Attachment
    {
        $lines = [];

        $lines = array_merge($lines, $this->buildSalesChannelLines(null, 'Global'));

        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->getSalesChannels($context) as $salesChannel) {
            $lines = array_merge($lines, $this->buildSalesChannelLines(
                $salesChannel->getId(),
                (string) $salesChannel->getTranslation('name')
            ));
        }

        $content = implode("\r\n", $lines);

        return new Attachment($content, 'plugin_configuration.txt', 'text/plain');
    }

    /**
     * @return string[]
     */
    private function buildSalesChannelLines(?string $salesChannelId, string $label): array
    {
        $lines = [];
        $lines[] = '[ ' . $label . ' ]';

        try {
            $apiSettings = $this->settingsService->getApiSettings($salesChannelId);

            $liveKeyStatus = $this->validateApiKey($apiSettings->getLiveApiKey());
            $testKeyStatus = $this->validateApiKey($apiSettings->getTestApiKey());

            $lines[] = 'Mode: ' . ($apiSettings->isTestMode() ? 'Test' : 'Live');
            $lines[] = 'Live API Key: ' . $liveKeyStatus;
            $lines[] = 'Test API Key: ' . $testKeyStatus;
            $lines[] = 'Profile ID: ' . ($apiSettings->getProfileId() ?: 'Empty');
            $lines[] = '';

            foreach ($this->settingsService->getPaymentSettings($salesChannelId)->getVars() as $key => $value) {
                $lines[] = $key . ': ' . $this->formatValue($value);
            }

            $lines[] = '';
        } catch (\Throwable $e) {
            $this->logger->error('Failed to collect readable config for support attachment: ' . $e->getMessage());
            $lines[] = 'Error collecting config: ' . $e->getMessage();
            $lines[] = '';
        }

        return $lines;
    }

    private function validateApiKey(string $apiKey): string
    {
        if ($apiKey === '') {
            return 'Empty';
        }

        try {
            $client = $this->clientFactory->createForKey($apiKey);
            $response = $client->get('profiles/me');

            return $response->getStatusCode() === 200 ? 'Valid' : 'Invalid';
        } catch (\Throwable $e) {
            return 'Invalid';
        }
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Enabled' : 'Disabled';
        }

        if ($value === null || $value === '') {
            return 'Empty';
        }

        return (string) $value;
    }

    private function getSalesChannels(Context $context): SalesChannelCollection
    {
        /** @var SalesChannelCollection */
        return $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();
    }
}
