<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Attachment\Generator;

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

final class JsonConfigGenerator implements AttachmentGeneratorInterface
{
    private const SENSITIVE_KEYS = ['testApiKey', 'liveApiKey'];

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(Context $context): Attachment
    {
        $configs = [];

        $configs[] = $this->buildSalesChannelConfig(null, 'Global');

        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->getSalesChannels($context) as $salesChannel) {
            $configs[] = $this->buildSalesChannelConfig(
                $salesChannel->getId(),
                (string) $salesChannel->getTranslation('name')
            );
        }

        $content = (string) json_encode($configs, JSON_PRETTY_PRINT);

        return new Attachment($content, 'plugin_configuration.json', 'application/json');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSalesChannelConfig(?string $salesChannelId, string $label): array
    {
        try {
            $apiSettings = $this->settingsService->getApiSettings($salesChannelId);
            $apiVars = $apiSettings->getVars();
            foreach (self::SENSITIVE_KEYS as $key) {
                $apiVars[$key] = '(hidden)';
            }

            return [
                'label' => $label,
                'config' => [
                    'api' => $apiVars,
                    'payment' => $this->settingsService->getPaymentSettings($salesChannelId)->getVars(),
                    'logger' => $this->settingsService->getLoggerSettings($salesChannelId)->getVars(),
                    'creditCard' => $this->settingsService->getCreditCardSettings($salesChannelId)->getVars(),
                    'applePay' => $this->settingsService->getApplePaySettings($salesChannelId)->getVars(),
                    'payPalExpress' => $this->settingsService->getPaypalExpressSettings($salesChannelId)->getVars(),
                    'account' => $this->settingsService->getAccountSettings($salesChannelId)->getVars(),
                    'orderState' => $this->settingsService->getOrderStateSettings($salesChannelId)->getVars(),
                    'refund' => $this->settingsService->getRefundSettings($salesChannelId)->getVars(),
                    'subscription' => $this->settingsService->getSubscriptionSettings($salesChannelId)->getVars(),
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to collect config for support attachment: ' . $e->getMessage());

            return ['label' => $label, 'config' => []];
        }
    }

    private function getSalesChannels(Context $context): SalesChannelCollection
    {
        /** @var SalesChannelCollection */
        return $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();
    }
}
