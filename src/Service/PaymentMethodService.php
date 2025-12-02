<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\IngHomePayPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayLaterPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayNowPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaSliceItPayment;
use Kiener\MolliePayments\Handler\Method\SofortPayment;
use Kiener\MolliePayments\Repository\MediaRepository;
use Kiener\MolliePayments\Repository\PaymentMethodRepository;
use Kiener\MolliePayments\Service\HttpClient\HttpClientInterface;
use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    public function __construct(VersionCompare $versionCompare,
        MediaService $mediaService,
        MediaRepository $mediaRepository,
        PaymentMethodRepository $paymentRepository,
        PluginIdProvider $pluginIdProvider,
        HttpClientInterface $httpClient,
        PayPalExpressConfig $payPalExpressConfig)
    {
        $this->mediaService = $mediaService;
        $this->mediaRepository = $mediaRepository;
        $this->paymentRepository = $paymentRepository;
        $this->pluginIdProvider = $pluginIdProvider;
        $this->httpClient = $httpClient;

        $this->versionCompare = $versionCompare;
        $this->payPalExpressConfig = $payPalExpressConfig;
    }

    public function installAndActivatePaymentMethods(Context $context): void
    {
        // install payment methods that are not allowed anymore.
        // we still need the min the database
        // but always disable them :)
        $this->disablePaymentMethod(IngHomePayPayment::class, $context);
        $this->disablePaymentMethod(KlarnaPayLaterPayment::class, $context);
        $this->disablePaymentMethod(KlarnaPayNowPayment::class, $context);
        $this->disablePaymentMethod(KlarnaSliceItPayment::class, $context);
        $this->disablePaymentMethod(SofortPayment::class, $context);
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

    public function isPaidApplePayTransaction(OrderTransactionEntity $transaction, Order $mollieOrder): bool
    {
        $paymentMethodId = $transaction->getPaymentMethodId();
        $paymentMethod = $transaction->getPaymentMethod();

        if (! $paymentMethod instanceof PaymentMethodEntity) {
            $criteria = new Criteria([$paymentMethodId]);
            /** @var PaymentMethodEntity $paymentMethod */
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
        /** @var ?PaymentMethodEntity $paymentMethod */
        $paymentMethod = $paymentMethods->first();
        if ($paymentMethod === null) {
            return null;
        }

        return $paymentMethod;
    }
}
