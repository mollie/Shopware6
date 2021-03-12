<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;


use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\TokenAnonymizer;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\MollieApiClient;
use Monolog\Logger;
use RuntimeException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ApiClientConfigurator
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var TokenAnonymizer
     */
    private $anonymizer;

    /**
     * @var LoggerService
     */
    private $logger;

    public function __construct(
        SettingsService $settingsService,
        TokenAnonymizer $anonymizer,
        LoggerService $logger)
    {
        $this->settingsService = $settingsService;
        $this->anonymizer = $anonymizer;
        $this->logger = $logger;
    }

    public function configure(MollieApiClient $client, SalesChannelContext $salesChannelContext): void
    {
        try {
            /** @var MollieSettingStruct $settings */
            $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannelId());

            /** @var string $apiKey */
            $apiKey = $settings->isTestMode() ? $settings->getTestApiKey() : $settings->getLiveApiKey();

            // Log the used API keys
            if ($settings->isDebugMode()) {
                $anonymizedApiKey = $this->anonymizer->anonymize($apiKey);
                $this->logger->addEntry(
                    sprintf('Selected API key %s for sales channel %s', $anonymizedApiKey, $salesChannelContext->getSalesChannel()->getName()),
                    $salesChannelContext->getContext(),
                    null,
                    [
                        'apiKey' => $anonymizedApiKey,
                    ]
                );
            }

            // Set the API key
            $client->setApiKey($apiKey);
        } catch (InconsistentCriteriaIdsException $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $salesChannelContext->getContext(),
                $e,
                [
                    'function' => 'set-mollie-api-key',
                ],
                Logger::ERROR
            );

            throw new RuntimeException(sprintf('Could not set Mollie Api Key, error: %s', $e->getMessage()));
        }
    }
}
