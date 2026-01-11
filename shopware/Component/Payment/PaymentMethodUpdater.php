<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PaymentMethodUpdater implements PaymentMethodUpdaterInterface
{
    /**
     * @param EntityRepository<OrderTransactionCollection<OrderTransactionEntity>> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function updatePaymentMethod(PaymentMethodExtension $paymentMethodExtension, PaymentMethod $molliePaymentMethod, string $transactionId, string $orderNumber, string $salesChannelId, Context $context): string
    {
        $shopwarePaymentMethod = $paymentMethodExtension->getPaymentMethod();
        $logData = [
            'transactionId' => $transactionId,
            'molliePaymentMethod' => $molliePaymentMethod->value,
            'orderNumber' => $orderNumber,
            'shopwarePaymentMethod' => $shopwarePaymentMethod->value,
        ];

        $this->logger->info('Change payment method if changed', $logData);

        if ($shopwarePaymentMethod === $molliePaymentMethod) {
            $this->logger->debug('Payment methods are the same', $logData);

            return $paymentMethodExtension->getId();
        }

        if ($shopwarePaymentMethod === PaymentMethod::APPLEPAY && $molliePaymentMethod === PaymentMethod::CREDIT_CARD) {
            $this->logger->debug('Apple Pay payment methods are stored as credit card in mollie, no change needed', $logData);

            return $paymentMethodExtension->getId();
        }

        $this->logger->debug('Payment methods are different, try to find payment method based on mollies payment method name', $logData);

        $newPaymentMethodId = $this->paymentMethodRepository->getIdByPaymentMethod($molliePaymentMethod, $salesChannelId, $context);

        if ($newPaymentMethodId === null) {
            $this->logger->error('Failed to find payment payment method based on "molliePaymentMethod" in database', $logData);
            $message = sprintf('The Transaction has %s set as payment method in Mollie, but this payment method does not exists or not enabled in shopware',$molliePaymentMethod->value);
            throw new \RuntimeException($message);
        }

        $this->orderTransactionRepository->upsert([
            [
                'id' => $transactionId,
                'paymentMethodId' => $newPaymentMethodId
            ]
        ], $context);

        $this->logger->info('Changed payment methods for transaction', $logData);

        return $newPaymentMethodId;
    }
}
