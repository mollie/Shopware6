<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Handler\Method\AlmaPayment;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BancomatPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
use Kiener\MolliePayments\Handler\Method\BilliePayment;
use Kiener\MolliePayments\Handler\Method\BlikPayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\EpsPayment;
use Kiener\MolliePayments\Handler\Method\GiftCardPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Handler\Method\In3Payment;
use Kiener\MolliePayments\Handler\Method\IngHomePayPayment;
use Kiener\MolliePayments\Handler\Method\KbcPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaOnePayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayLaterPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayNowPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaSliceItPayment;
use Kiener\MolliePayments\Handler\Method\MbWayPayment;
use Kiener\MolliePayments\Handler\Method\MultibancoPayment;
use Kiener\MolliePayments\Handler\Method\MyBankPayment;
use Kiener\MolliePayments\Handler\Method\PayByBankPayment;
use Kiener\MolliePayments\Handler\Method\PayconiqPayment;
use Kiener\MolliePayments\Handler\Method\PayPalExpressPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\PaySafeCardPayment;
use Kiener\MolliePayments\Handler\Method\PosPayment;
use Kiener\MolliePayments\Handler\Method\Przelewy24Payment;
use Kiener\MolliePayments\Handler\Method\RivertyPayment;
use Kiener\MolliePayments\Handler\Method\SatispayPayment;
use Kiener\MolliePayments\Handler\Method\SofortPayment;
use Kiener\MolliePayments\Handler\Method\SwishPayment;
use Kiener\MolliePayments\Handler\Method\TrustlyPayment;
use Kiener\MolliePayments\Handler\Method\TwintPayment;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\MolliePayments;
use Kiener\MolliePayments\Repository\MediaRepository;
use Kiener\MolliePayments\Repository\PaymentMethodRepository;
use Kiener\MolliePayments\Service\HttpClient\HttpClientInterface;
use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentMethodService
{
    public const TECHNICAL_NAME_PREFIX = 'payment_mollie_';

    /**
     * @var MediaService
     */
    private $mediaService;

    /**
     * @var PaymentMethodRepository
     */
    private $paymentRepository;

    /**
     * @var PluginIdProvider
     */
    private $pluginIdProvider;

    /**
     * @var MediaRepository
     */
    private $mediaRepository;

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * @var VersionCompare
     */
    private $versionCompare;
    private PayPalExpressConfig $payPalExpressConfig;

    /**
     * @param MediaRepository $mediaRepository
     */
    public function __construct(string $shopwareVersion, MediaService $mediaService, MediaRepository $mediaRepository, PaymentMethodRepository $paymentRepository, PluginIdProvider $pluginIdProvider, HttpClientInterface $httpClient, PayPalExpressConfig $payPalExpressConfig)
    {
        $this->mediaService = $mediaService;
        $this->mediaRepository = $mediaRepository;
        $this->paymentRepository = $paymentRepository;
        $this->pluginIdProvider = $pluginIdProvider;
        $this->httpClient = $httpClient;

        $this->versionCompare = new VersionCompare($shopwareVersion);
        $this->payPalExpressConfig = $payPalExpressConfig;
    }

    public function installAndActivatePaymentMethods(Context $context): void
    {
        // install payment methods that are not allowed anymore.
        // we still need the min the database
        // but always disable them :)
        $this->disablePaymentMethod(IngHomePayPayment::class, $context);

        if (! $this->payPalExpressConfig->isEnabled()) {
            $this->disablePaymentMethod(PayPalExpressPayment::class, $context);
        }

        // Get installable payment methods
        $installablePaymentMethods = $this->getInstallablePaymentMethods();

        if (empty($installablePaymentMethods)) {
            return;
        }

        // Check which payment methods from Mollie are already installed in the shop
        $installedPaymentMethodHandlers = $this->getInstalledPaymentMethodHandlers($this->getPaymentHandlers(), $context);

        // Add payment methods
        $this->addPaymentMethods($installablePaymentMethods, $context);

        // Activate newly installed payment methods
        $this->activatePaymentMethods(
            $installablePaymentMethods,
            $installedPaymentMethodHandlers,
            $context
        );
    }

    /**
     * @param array<mixed> $paymentMethods
     */
    public function addPaymentMethods(array $paymentMethods, Context $context): void
    {
        // Get the plugin ID
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(MolliePayments::class, $context);

        $upsertData = [];

        foreach ($paymentMethods as $paymentMethod) {
            $identifier = $paymentMethod['handler'];

            // Upload icon to the media repository
            $mediaId = $this->getMediaId($paymentMethod, $context);

            try {
                $existingPaymentMethod = $this->getPaymentMethod($identifier, $context);
            } catch (InconsistentCriteriaIdsException $e) {
                $existingPaymentMethod = null;
            }

            $technicalName = '';

            if ($existingPaymentMethod instanceof PaymentMethodEntity) {
                $paymentMethodData = [
                    // ALWAYS ADD THE ID, otherwise upsert would create NEW entries!
                    'id' => $existingPaymentMethod->getId(),
                    'handlerIdentifier' => $paymentMethod['handler'],
                    // ------------------------------------------
                    // make sure to repair some fields in here
                    // so that Mollie does always work for our wonderful customers :)
                    'pluginId' => $pluginId,
                    // ------------------------------------------
                    // unfortunately some fields are required (*sigh)
                    // so we need to provide those with the value of
                    // the existing method!!!
                    'name' => $existingPaymentMethod->getName(),
                ];
                $translations = $existingPaymentMethod->getTranslations();

                if ($translations !== null) {
                    $paymentMethodData['translations'][Defaults::LANGUAGE_SYSTEM] = [
                        'name' => $existingPaymentMethod->getName(),
                    ];

                    foreach ($translations as $translation) {
                        $paymentMethodData['translations'][$translation->getLanguageId()] = [
                            'name' => $translation->getName(),
                        ];
                    }
                }

                if ($this->versionCompare->gte('6.5.7.0')) {
                    // we do a string cast here, since getTechnicalName will be not nullable in the future
                    /** @phpstan-ignore-next-line  */
                    $technicalName = (string) $existingPaymentMethod->getTechnicalName();
                }
            } else {
                // let's create a full parameter list of everything
                // that our new payment method needs to have
                $paymentMethodData = [
                    'handlerIdentifier' => $paymentMethod['handler'],
                    'pluginId' => $pluginId,
                    // ------------------------------------------
                    'name' => $paymentMethod['description'],
                    'description' => '',
                    'mediaId' => $mediaId,
                    'afterOrderEnabled' => true,
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => $paymentMethod['description'],
                        ],
                    ],
                ];
            }

            if ($technicalName === '') {
                $technicalName = self::TECHNICAL_NAME_PREFIX . $paymentMethod['name'];
            }

            // custom field name is required to be specific, because we use it in the template to display components
            $paymentMethodData['customFields'] = [
                'mollie_payment_method_name' => $paymentMethod['name'],
            ];

            // starting with Shopware 6.5.7.0 this has to be filled out
            // so that you can still save the payment method in the administration
            if ($this->versionCompare->gte('6.5.7.0')) {
                $paymentMethodData['technicalName'] = $technicalName;
            }

            $upsertData[] = $paymentMethodData;
        }

        if (count($upsertData) > 0) {
            $this->paymentRepository->getRepository()->upsert($upsertData, $context);
        }
    }

    /**
     * @param array<mixed> $installableHandlers
     *
     * @return array<mixed>
     */
    public function getInstalledPaymentMethodHandlers(array $installableHandlers, Context $context): array
    {
        $installedHandlers = [];
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new ContainsFilter('handlerIdentifier', 'MolliePayments'));

        $paymentMethods = $this->paymentRepository->getRepository()->search($paymentCriteria, $context);

        if (! $paymentMethods->count()) {
            return $installableHandlers;
        }

        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods->getEntities() as $paymentMethod) {
            if (! in_array($paymentMethod->getHandlerIdentifier(), $installableHandlers, true)) {
                continue;
            }

            $installedHandlers[] = $paymentMethod->getHandlerIdentifier();
        }

        return $installedHandlers;
    }

    /**
     * Activate payment methods in Shopware.
     *
     * @param array<mixed> $paymentMethods
     * @param array<mixed> $installedHandlers
     */
    public function activatePaymentMethods(array $paymentMethods, array $installedHandlers, Context $context): void
    {
        if (! empty($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                if (
                    ! isset($paymentMethod['handler'])
                    || in_array($paymentMethod['handler'], $installedHandlers, true)
                ) {
                    continue;
                }

                $existingPaymentMethod = $this->getPaymentMethod($paymentMethod['handler'], $context);

                if (isset($existingPaymentMethod)) {
                    $this->setPaymentMethodActivated($existingPaymentMethod->getId(), true, $context);
                }
            }
        }
    }

    public function disablePaymentMethod(string $handlerName, Context $context): void
    {
        $existingPaymentMethod = $this->getPaymentMethod($handlerName, $context);

        if (isset($existingPaymentMethod)) {
            $this->setPaymentMethodActivated(
                $existingPaymentMethod->getId(),
                false,
                $context
            );
        }
    }

    /**
     * Activates a payment method in Shopware
     */
    public function setPaymentMethodActivated(string $paymentMethodId, bool $active, Context $context): EntityWrittenContainerEvent
    {
        return $this->paymentRepository->getRepository()->upsert(
            [
                [
                    'id' => $paymentMethodId,
                    'active' => $active,
                ],
            ],
            $context
        );
    }

    /**
     * Get payment method by ID.
     *
     * @param string $id
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function getPaymentMethodById($id): ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('id', $id));

        // Get payment methods
        $paymentMethods = $this->paymentRepository->getRepository()->search($paymentCriteria, Context::createDefaultContext());

        if ($paymentMethods->getTotal() === 0) {
            return null;
        }

        return $paymentMethods->first();
    }

    /**
     * Get an array of installable payment methods for Mollie.
     *
     * @return array<mixed>
     */
    public function getInstallablePaymentMethods(): array
    {
        $installablePaymentMethods = $this->getPaymentHandlers();

        if (count($installablePaymentMethods) <= 0) {
            return [];
        }

        $paymentMethods = [];

        foreach ($installablePaymentMethods as $installablePaymentMethod) {
            $paymentMethods[] = [
                'name' => constant($installablePaymentMethod . '::PAYMENT_METHOD_NAME'),
                'description' => constant($installablePaymentMethod . '::PAYMENT_METHOD_DESCRIPTION'),
                'handler' => $installablePaymentMethod,
            ];
        }

        return $paymentMethods;
    }

    /**
     * Returns an array of payment handlers.
     *
     * @return array<mixed>
     */
    public function getPaymentHandlers(): array
    {
        $paymentHandlers = [
            ApplePayPayment::class,
            BanContactPayment::class,
            BankTransferPayment::class,
            BilliePayment::class,
            BelfiusPayment::class,
            CreditCardPayment::class,
            EpsPayment::class,
            GiftCardPayment::class,
            iDealPayment::class,
            KbcPayment::class,
            KlarnaPayLaterPayment::class,
            KlarnaPayNowPayment::class,
            KlarnaSliceItPayment::class,
            KlarnaOnePayment::class,
            PayPalPayment::class,
            PaySafeCardPayment::class,
            Przelewy24Payment::class,
            SofortPayment::class,
            VoucherPayment::class,
            In3Payment::class,
            PosPayment::class,
            TwintPayment::class,
            BlikPayment::class,
            BancomatPayment::class,
            MyBankPayment::class,
            AlmaPayment::class,
            TrustlyPayment::class,
            PayconiqPayment::class,
            RivertyPayment::class,
            SatispayPayment::class,
            PayByBankPayment::class,
            MbWayPayment::class,
            MultibancoPayment::class,
            SwishPayment::class,
            // IngHomePayPayment::class, // not allowed anymore
            // DirectDebitPayment::class, // only allowed when updating subsriptions, aka => not allowed anymore
        ];

        if ($this->payPalExpressConfig->isEnabled()) {
            $paymentHandlers[] = PayPalExpressPayment::class;
        }

        return $paymentHandlers;
    }

    public function isPaidApplePayTransaction(OrderTransactionEntity $transaction, Order $mollieOrder): bool
    {
        $paymentMethodId = $transaction->getPaymentMethodId();
        $paymentMethod = $transaction->getPaymentMethod();

        if (! $paymentMethod instanceof PaymentMethodEntity) {
            $criteria = new Criteria([$paymentMethodId]);
            $paymentMethod = $this->paymentRepository->getRepository()->search($criteria, Context::createDefaultContext())->first();
        }

        return $paymentMethod->getHandlerIdentifier() === ApplePayPayment::class && $mollieOrder->isPaid() === true;
    }

    /**
     * Get payment method ID by name.
     *
     * @param string $handlerIdentifier
     */
    private function getPaymentMethod($handlerIdentifier, Context $context): ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));
        $paymentCriteria->addAssociation('translations');

        // Get payment IDs
        $paymentMethods = $this->paymentRepository->getRepository()->search($paymentCriteria, $context);

        if ($paymentMethods->getTotal() === 0) {
            return null;
        }

        return $paymentMethods->first();
    }

    /**
     * Retrieve the icon from the database, or add it.
     *
     * @param array<mixed> $paymentMethod
     */
    private function getMediaId(array $paymentMethod, Context $context): ?string
    {
        $name = $paymentMethod['name'];

        if ($name === PayPalExpressPayment::PAYMENT_METHOD_NAME) {
            $name = PayPalPayment::PAYMENT_METHOD_NAME;
        }

        /** @var string $fileName */
        $fileName = $name . '-icon';

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        /** @var MediaCollection $icons */
        $icons = $this->mediaRepository->getRepository()->search($criteria, $context);

        if ($icons->count() && $icons->first() !== null) {
            return $icons->first()->getId();
        }

        // Add icon to the media library
        $iconMime = 'image/svg+xml';
        $iconExt = 'svg';
        $iconBlob = $this->downloadFile('https://www.mollie.com/external/icons/payment-methods/' . $paymentMethod['name'] . '.svg');

        if ($iconBlob === '') {
            $iconBlob = $this->downloadFile('https://www.mollie.com/external/icons/payment-methods/' . $paymentMethod['name'] . '.png');
            $iconMime = 'image/png';
            $iconExt = 'png';
        }

        if ($iconBlob === '') {
            return null;
        }

        return $this->mediaService->saveFile(
            $iconBlob,
            $iconExt,
            $iconMime,
            $fileName,
            $context,
            'Mollie Payments - Icons',
            null,
            false
        );
    }

    private function downloadFile(string $url): string
    {
        $response = $this->httpClient->sendRequest('GET', $url);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return '';
        }

        return $response->getBody();
    }
}
