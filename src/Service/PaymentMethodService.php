<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
use Kiener\MolliePayments\Handler\Method\BilliePayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\EpsPayment;
use Kiener\MolliePayments\Handler\Method\GiftCardPayment;
use Kiener\MolliePayments\Handler\Method\GiroPayPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Handler\Method\In3Payment;
use Kiener\MolliePayments\Handler\Method\IngHomePayPayment;
use Kiener\MolliePayments\Handler\Method\KbcPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayLaterPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayNowPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaSliceItPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\PaySafeCardPayment;
use Kiener\MolliePayments\Handler\Method\Przelewy24Payment;
use Kiener\MolliePayments\Handler\Method\SofortPayment;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\MolliePayments;
use Kiener\MolliePayments\Repository\Media\MediaRepository;
use Kiener\MolliePayments\Repository\Media\MediaRepositoryInterface;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use Kiener\MolliePayments\Service\HttpClient\HttpClientInterface;
use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentMethodService
{
    /** @var MediaService */
    private $mediaService;

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentRepository;

    /** @var PluginIdProvider */
    private $pluginIdProvider;

    /**
     * @var MediaRepositoryInterface
     */
    private $mediaRepository;

    /** @var HttpClientInterface */
    private $httpClient;


    /**
     * @param MediaService $mediaService
     * @param MediaRepositoryInterface $mediaRepository
     * @param PaymentMethodRepositoryInterface $paymentRepository
     * @param PluginIdProvider $pluginIdProvider
     * @param HttpClientInterface $httpClient
     */
    public function __construct(MediaService $mediaService, MediaRepositoryInterface $mediaRepository, PaymentMethodRepositoryInterface $paymentRepository, PluginIdProvider $pluginIdProvider, HttpClientInterface $httpClient)
    {
        $this->mediaService = $mediaService;
        $this->mediaRepository = $mediaRepository;
        $this->paymentRepository = $paymentRepository;
        $this->pluginIdProvider = $pluginIdProvider;
        $this->httpClient = $httpClient;
    }


    /**
     * @param Context $context
     */
    public function installAndActivatePaymentMethods(Context $context): void
    {
        # install payment methods that are not allowed anymore.
        # we still need the min the database
        # but always disable them :)
        $this->disablePaymentMethod(IngHomePayPayment::class, $context);


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
     * @param Context $context
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


            if ($existingPaymentMethod instanceof PaymentMethodEntity) {
                $paymentMethodData = [
                    # ALWAYS ADD THE ID, otherwise upsert would create NEW entries!
                    'id' => $existingPaymentMethod->getId(),
                    'handlerIdentifier' => $paymentMethod['handler'],
                    # ------------------------------------------
                    # make sure to repair some fields in here
                    # so that Mollie does always work for our wonderful customers :)
                    'pluginId' => $pluginId,
                    'customFields' => [
                        'mollie_payment_method_name' => $paymentMethod['name'],
                    ],
                    # ------------------------------------------
                    # unfortunately some fields are required (*sigh)
                    # so we need to provide those with the value of
                    # the existing method!!!
                    'name' => $existingPaymentMethod->getName(),
                ];

                $upsertData[] = $paymentMethodData;
            } else {
                # let's create a full parameter list of everything
                # that our new payment method needs to have
                $paymentMethodData = [
                    'handlerIdentifier' => $paymentMethod['handler'],
                    'pluginId' => $pluginId,
                    # ------------------------------------------
                    'name' => $paymentMethod['description'],
                    'description' => '',
                    'mediaId' => $mediaId,
                    'afterOrderEnabled' => true,
                    # ------------------------------------------
                    'customFields' => [
                        'mollie_payment_method_name' => $paymentMethod['name'],
                    ],
                ];

                $upsertData[] = $paymentMethodData;
            }
        }

        if (count($upsertData) > 0) {
            $this->paymentRepository->upsert($upsertData, $context);
        }
    }

    /**
     * @param array<mixed> $installableHandlers
     * @param Context $context
     * @return array<mixed>
     */
    public function getInstalledPaymentMethodHandlers(array $installableHandlers, Context $context): array
    {
        $installedHandlers = [];
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new ContainsFilter('handlerIdentifier', 'MolliePayments'));

        $paymentMethods = $this->paymentRepository->search($paymentCriteria, $context);

        if (!$paymentMethods->count()) {
            return $installableHandlers;
        }

        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods->getEntities() as $paymentMethod) {
            if (!in_array($paymentMethod->getHandlerIdentifier(), $installableHandlers, true)) {
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
     * @param Context $context
     */
    public function activatePaymentMethods(array $paymentMethods, array $installedHandlers, Context $context): void
    {
        if (!empty($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                if (
                    !isset($paymentMethod['handler']) ||
                    in_array($paymentMethod['handler'], $installedHandlers, true)
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

    /**
     * @param string $handlerName
     * @param Context $context
     * @return void
     */
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
     *
     * @param string $paymentMethodId
     * @param bool $active
     * @param Context $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function setPaymentMethodActivated(
        string  $paymentMethodId,
        bool    $active,
        Context $context
    ): EntityWrittenContainerEvent {
        return $this->paymentRepository->upsert(
            [
                [
                    'id' => $paymentMethodId,
                    'active' => $active
                ]
            ],
            $context
        );
    }

    /**
     * Get payment method by ID.
     *
     * @param string $id
     * @throws InconsistentCriteriaIdsException
     * @return PaymentMethodEntity
     */
    public function getPaymentMethodById($id): ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('id', $id));

        // Get payment methods
        $paymentMethods = $this->paymentRepository->search($paymentCriteria, Context::createDefaultContext());

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
     * Get payment method ID by name.
     *
     * @param string $handlerIdentifier
     * @param Context $context
     *
     * @return null|PaymentMethodEntity
     */
    private function getPaymentMethod($handlerIdentifier, Context $context): ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

        // Get payment IDs
        $paymentMethods = $this->paymentRepository->search($paymentCriteria, $context);

        if ($paymentMethods->getTotal() === 0) {
            return null;
        }

        return $paymentMethods->first();
    }

    /**
     * Returns an array of payment handlers.
     *
     * @return array<mixed>
     */
    public function getPaymentHandlers(): array
    {
        return [
            ApplePayPayment::class,
            BanContactPayment::class,
            BankTransferPayment::class,
            BilliePayment::class,
            BelfiusPayment::class,
            CreditCardPayment::class,
            EpsPayment::class,
            GiftCardPayment::class,
            GiroPayPayment::class,
            iDealPayment::class,
            KbcPayment::class,
            KlarnaPayLaterPayment::class,
            KlarnaPayNowPayment::class,
            KlarnaSliceItPayment::class,
            PayPalPayment::class,
            PaySafeCardPayment::class,
            Przelewy24Payment::class,
            SofortPayment::class,
            VoucherPayment::class,
            In3Payment::class
            // IngHomePayPayment::class, // not allowed anymore
            // DirectDebitPayment::class, // only allowed when updating subsriptions, aka => not allowed anymore
        ];
    }

    /**
     * Retrieve the icon from the database, or add it.
     *
     * @param array<mixed> $paymentMethod
     * @param Context $context
     *
     * @return string
     */
    private function getMediaId(array $paymentMethod, Context $context): ?string
    {
        /** @var string $fileName */
        $fileName = $paymentMethod['name'] . '-icon';

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        /** @var MediaCollection $icons */
        $icons = $this->mediaRepository->search($criteria, $context);

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

    /**
     * @param OrderTransactionEntity $transaction
     * @param Order $mollieOrder
     * @return bool
     */
    public function isPaidApplePayTransaction(OrderTransactionEntity $transaction, Order $mollieOrder): bool
    {
        $paymentMethodId = $transaction->getPaymentMethodId();
        $paymentMethod = $transaction->getPaymentMethod();

        if (!$paymentMethod instanceof PaymentMethodEntity) {
            $criteria = new Criteria([$paymentMethodId]);
            $paymentMethod = $this->paymentRepository->search($criteria, Context::createDefaultContext())->first();
        }

        return $paymentMethod->getHandlerIdentifier() === ApplePayPayment::class && $mollieOrder->isPaid() === true;
    }

    /**
     * @param string $url
     * @return string
     */
    private function downloadFile(string $url): string
    {
        $response = $this->httpClient->sendRequest('GET', $url);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return '';
        }

        return $response->getBody();
    }
}
