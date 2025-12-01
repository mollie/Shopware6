<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Kiener\MolliePayments\MolliePayments;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;
use Mollie\Shopware\Mollie;
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
        private PaymentMethodRepository $molliePaymentMethodRepository,
        #[Autowire(service: 'payment_method.repository')]
        private EntityRepository $shopwarePaymentMethodRepository,
        #[Autowire(service: 'media.repository')]
        private EntityRepository $mediaRepository,
        private MediaService $mediaService,
        private FileFetcher $fileFetcher,
        private PluginIdProvider $pluginIdProvider,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
        parent::__construct();
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
        $molliePaymentMethods = $this->molliePaymentMethodRepository->getPaymentMethods();
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(MolliePayments::class, $context);

        $mapping = [];
        $iconMapping = [];
        /** @var AbstractMolliePaymentHandler $paymentHandler */
        foreach ($molliePaymentMethods as $paymentHandler) {
            $handlerIdentifier = get_class($paymentHandler);
            $paymentMethodTechnicalName = $paymentHandler->getPaymentMethod()->value;
            $paymentMethodName = $paymentHandler->getName();
            $paymentMethodDescription = $paymentHandler->getDescription();
            $isDeprecatedMethod = $paymentHandler instanceof DeprecatedMethodAwareInterface;
            $mapping[$handlerIdentifier] = [
                'id' => Uuid::fromStringToHex('mollie-payment-' . $paymentMethodTechnicalName),
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true,
                'technicalName' => 'payment_mollie_' . $paymentMethodTechnicalName,
                'handlerIdentifier' => $handlerIdentifier,
                'name' => $paymentMethodName,
                'description' => $paymentMethodDescription,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => $paymentMethodName,
                        'description' => $paymentMethodDescription,
                    ],
                ],
                'customFields' => [
                    Mollie::EXTENSION => [
                        'payment_method' => $paymentMethodTechnicalName,
                    ]
                ],
                'active' => ! $isDeprecatedMethod,
            ];
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
                $currentHandlerIdentifier = $iconMapping[$fileName];
                $mediaFile = $this->loadMedia($fileName);
                if ($mediaFile === null) {
                    unset($iconMapping[$fileName]);
                    continue;
                }
                $mediaId = $this->mediaService->saveMediaFile($mediaFile, $fileName, $context, 'payment_method', null, false);
                $mapping[$currentHandlerIdentifier]['mediaId'] = $mediaId;
                unset($iconMapping[$fileName]);
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
                $changedData = [
                    'id' => $paymentMethodEntity->getId(),
                    'afterOrderEnabled' => $paymentMethodEntity->getAfterOrderEnabled(),
                    'technicalName' => (string) $paymentMethodEntity->getTechnicalName(),
                    'name' => $paymentMethodEntity->getName(),
                    'description' => $paymentMethodEntity->getDescription(),
                    'active' => $paymentMethodEntity->getActive(),
                    'customFields' => $paymentMethodEntity->getCustomFields(),
                ];
                $translations = $paymentMethodEntity->getTranslations();

                if ($translations !== null) {
                    $changedData['translations'][Defaults::LANGUAGE_SYSTEM] = [
                        'name' => $paymentMethodEntity->getName(),
                        'description' => $paymentMethodEntity->getDescription(),
                    ];

                    foreach ($translations as $translation) {
                        $changedData['translations'][$translation->getLanguageId()] = [
                            'name' => $translation->getName(),
                            'description' => $translation->getDescription(),
                        ];
                    }
                }

                $mapping[$paymentMethodEntity->getHandlerIdentifier()] = array_replace($mapping[$paymentMethodEntity->getHandlerIdentifier()], $changedData);
            }
        }

        return array_values($mapping);
    }

    private function loadMedia(string $fileName): ?MediaFile
    {
        $request = new Request();
        $extensions = ['.svg', '.png'];
        foreach ($extensions as $extension) {
            $url = 'https://www.mollie.com/external/icons/payment-methods/' . str_replace('-icon', '', $fileName) . $extension;
            try {
                $request->request->set('url', $url);

                return $this->fileFetcher->fetchFileFromURL($request, $fileName);
            } catch (MediaException $e) {
                $message = sprintf('Failed to load icon from url');
                $this->logger->warning($message,[
                    'exception' => $e->getMessage(),
                    'file' => $fileName,
                    'url' => $url,
                ]);
            }
        }

        $this->logger->error('Failed to load payment method icon, PNG and SVG',[
            'file' => $fileName,
            'url' => $url,
        ]);

        return null;
    }
}
