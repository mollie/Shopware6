<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Kiener\MolliePayments\MolliePayments;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\TestOnlyAwareInterface;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final class PaymentMethodInstaller
{
    /**
     * @param EntityRepository<PaymentMethodCollection<PaymentMethodEntity>> $shopwarePaymentMethodRepository
     * @param EntityRepository<MediaCollection<MediaEntity>> $mediaRepository
     */
    public function __construct(
        private PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: 'payment_method.repository')]
        private EntityRepository $shopwarePaymentMethodRepository,
        #[Autowire(service: 'media.repository')]
        private EntityRepository $mediaRepository,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        private MediaService $mediaService,
        private FileFetcher $fileFetcher,
        private PluginIdProvider $pluginIdProvider,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function install(Context $context): EntityWrittenContainerEvent
    {
        $upsertData = $this->loadPaymentMethodMapping($context);

        return $this->shopwarePaymentMethodRepository->upsert($upsertData, $context);
    }

    /**
     * @return array<mixed>
     */
    private function loadPaymentMethodMapping(Context $context): array
    {
        $molliePaymentMethods = $this->paymentHandlerLocator->getPaymentMethods();
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(MolliePayments::class, $context);
        $paypalExpressSettings = $this->settingsService->getPaypalExpressSettings();

        $mapping = [];
        $iconMapping = [];
        $handlers = [];
        /** @var AbstractMolliePaymentHandler $paymentHandler */
        foreach ($molliePaymentMethods as $paymentHandler) {
            $handlerIdentifier = get_class($paymentHandler);

            if ($paymentHandler instanceof TestOnlyAwareInterface) {
                continue;
            }

            if ($paymentHandler instanceof PayPalExpressPayment && ! $paypalExpressSettings->isEnabled()) {
                continue;
            }
            $paymentMethod = $paymentHandler->getPaymentMethod();
            $isDeprecatedMethod = $paymentHandler instanceof DeprecatedMethodAwareInterface;
            $paymentMethodName = $paymentHandler->getName();
            $paymentMethodTechnicalName = $paymentHandler->getTechnicalName();
            $mapping[$handlerIdentifier] = [
                'id' => Uuid::fromStringToHex('mollie-payment-' . $paymentMethodTechnicalName),
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true,
                'technicalName' => $paymentMethodTechnicalName,
                'handlerIdentifier' => $handlerIdentifier,
                'name' => $paymentMethodName,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => $paymentMethodName,
                    ],
                ],
                'customFields' => [
                    'mollie_payment_method_name' => $paymentMethod->value,
                ],
                'active' => $isDeprecatedMethod === false
            ];

            $handlers[$handlerIdentifier] = $paymentHandler;
            $iconMapping[$paymentHandler->getIconFileName()] = $handlerIdentifier;
        }

        $iconNames = array_keys($iconMapping);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('fileName', $iconNames));
        $iconNamesSearchResult = $this->mediaRepository->search($criteria, $context);
        if ($iconNamesSearchResult->getTotal() > 0) {
            /** @var MediaEntity $mediaEntity */
            foreach ($iconNamesSearchResult->getIterator() as $mediaEntity) {
                $fileName = (string) $mediaEntity->getFileName();
                $currentHandlerIdentifier = $iconMapping[$fileName];
                $mapping[$currentHandlerIdentifier]['mediaId'] = $mediaEntity->getId();
                unset($iconMapping[$fileName]);
            }
        }

        if (count($iconMapping) > 0) {
            foreach ($iconMapping as $fileName => $handlerIdentifier) {
                $mediaId = $this->installIcon($fileName, $context);
                if ($mediaId === null) {
                    continue;
                }
                $mapping[$handlerIdentifier]['mediaId'] = $mediaId;
            }
        }

        $handlerIdentifiers = array_keys($mapping);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('handlerIdentifier', $handlerIdentifiers));
        $criteria->addAssociation('translations');
        $paymentMethodSearchResult = $this->shopwarePaymentMethodRepository->search($criteria, $context);

        if ($paymentMethodSearchResult->getTotal() > 0) {
            /** @var PaymentMethodEntity $paymentMethodEntity */
            foreach ($paymentMethodSearchResult->getIterator() as $paymentMethodEntity) {
                $handlerIdentifier = $paymentMethodEntity->getHandlerIdentifier();

                // The handler always provides a non-empty name; keep it as fallback so we never
                // upsert an empty name when a translation row (e.g. the system language) is missing.
                $defaultName = (string) ($mapping[$handlerIdentifier]['name'] ?? '');
                $systemName = (string) $paymentMethodEntity->getName();
                if ($systemName === '') {
                    $systemName = $defaultName;
                }
                $description = $paymentMethodEntity->getDescription();

                $changedData = [
                    'id' => $paymentMethodEntity->getId(),
                    'afterOrderEnabled' => $paymentMethodEntity->getAfterOrderEnabled(),
                    'technicalName' => (string) $paymentMethodEntity->getTechnicalName(),
                    'name' => $systemName,
                    'description' => $description,
                    'active' => $paymentMethodEntity->getActive(),
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => $systemName,
                            'description' => $description,
                        ],
                    ],
                ];

                $translations = $paymentMethodEntity->getTranslations();
                if ($translations !== null) {
                    foreach ($translations as $translation) {
                        $translationName = (string) $translation->getName();
                        if ($translationName === '') {
                            $translationName = $defaultName;
                        }
                        $changedData['translations'][$translation->getLanguageId()] = [
                            'name' => $translationName,
                            'description' => $translation->getDescription(),
                        ];
                    }
                }

                $handler = $handlers[$handlerIdentifier] ?? null;

                if ($handler instanceof DeprecatedMethodAwareInterface) {
                    $changedData['active'] = false;
                }

                $mapping[$handlerIdentifier] = array_replace($mapping[$handlerIdentifier], $changedData);
            }
        }

        return array_values($mapping);
    }

    private function installIcon(string $fileName, Context $context): ?string
    {
        foreach (['.svg', '.png'] as $extension) {
            $mediaFile = $this->fetchIcon($fileName, $extension);
            if ($mediaFile === null) {
                continue;
            }

            try {
                return $this->mediaService->saveMediaFile($mediaFile, $fileName, $context, 'payment_method', null, false);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to save payment method icon, trying next format', [
                    'exception' => $e->getMessage(),
                    'file' => $fileName,
                    'extension' => $extension,
                ]);
            } finally {
                $this->cleanupTempFile($mediaFile);
            }
        }

        $this->logger->error('Failed to install payment method icon, all formats exhausted', [
            'file' => $fileName,
        ]);

        return null;
    }

    private function fetchIcon(string $fileName, string $extension): ?MediaFile
    {
        $url = 'https://www.mollie.com/external/icons/payment-methods/' . str_replace('-icon', '', $fileName) . $extension;
        $request = new Request();
        $request->request->set('url', $url);
        $request->query->set('extension', ltrim($extension, '.'));

        try {
            return $this->fileFetcher->fetchFileFromURL($request, $fileName);
        } catch (MediaException $e) {
            $this->logger->warning('Failed to fetch payment method icon', [
                'exception' => $e->getMessage(),
                'file' => $fileName,
                'url' => $url,
            ]);

            return null;
        }
    }

    private function cleanupTempFile(MediaFile $mediaFile): void
    {
        $tempFilePath = $mediaFile->getFileName();
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }
    }
}
