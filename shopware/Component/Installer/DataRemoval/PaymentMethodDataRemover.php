<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer\DataRemoval;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mollie payment methods that are still referenced (by an order transaction or as a sales channel
 * default) are deactivated and detached from sales channels - deleting them would break those
 * orders, whose invoices must stay valid. Methods that are not referenced anywhere are deleted.
 */
final class PaymentMethodDataRemover implements DataRemoverInterface
{
    /**
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Checkout\Payment\PaymentMethodEntity>> $paymentMethodRepository
     */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $molliePaymentMethodRepository,
        #[Autowire(service: 'payment_method.repository')]
        private readonly EntityRepository $paymentMethodRepository,
        private readonly Connection $connection,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function remove(Context $context): void
    {
        $molliePaymentMethods = $this->molliePaymentMethodRepository->findAllMollieMethods($context);

        $molliePaymentMethodIds = $molliePaymentMethods->getIds();
        if (count($molliePaymentMethodIds) === 0) {
            return;
        }

        $referencedIds = $this->findReferencedPaymentMethodIds($molliePaymentMethodIds);

        $deactivateUpdates = [];
        $detachIds = [];
        $deletes = [];
        foreach ($molliePaymentMethodIds as $paymentMethodId) {
            if (in_array($paymentMethodId, $referencedIds, true)) {
                $deactivateUpdates[] = ['id' => $paymentMethodId, 'active' => false];
                $detachIds[] = $paymentMethodId;
            } else {
                $deletes[] = ['id' => $paymentMethodId];
            }
        }

        if (count($deactivateUpdates) > 0) {
            $this->paymentMethodRepository->update($deactivateUpdates, $context);
            $this->detachFromSalesChannels($detachIds);
        }

        $deletedCount = count($deletes);
        if ($deletedCount > 0) {
            try {
                $this->paymentMethodRepository->delete($deletes, $context);
            } catch (\Throwable $exception) {
                // A method we considered unused is still referenced somewhere we did not check
                // (e.g. a customer default payment method). Never let uninstall fail on this -
                // deactivate and detach it instead.
                $this->logger->warning('Could not delete some Mollie payment methods, deactivating them instead', [
                    'error' => $exception->getMessage(),
                ]);

                $fallbackUpdates = [];
                $fallbackDetachIds = [];
                foreach ($deletes as $delete) {
                    $fallbackUpdates[] = ['id' => $delete['id'], 'active' => false];
                    $fallbackDetachIds[] = $delete['id'];
                }

                $this->paymentMethodRepository->update($fallbackUpdates, $context);
                $this->detachFromSalesChannels($fallbackDetachIds);

                $deletedCount = 0;
            }
        }

        $this->logger->info('Mollie payment methods processed on data removal', [
            'deactivated' => count($deactivateUpdates),
            'deleted' => $deletedCount,
        ]);
    }

    /**
     * @param string[] $paymentMethodIds
     *
     * @return string[] hex ids that must not be deleted
     */
    private function findReferencedPaymentMethodIds(array $paymentMethodIds): array
    {
        $binaryIds = [];
        foreach ($paymentMethodIds as $paymentMethodId) {
            $binaryIds[] = Uuid::fromHexToBytes($paymentMethodId);
        }

        $usedInOrders = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT payment_method_id FROM order_transaction WHERE payment_method_id IN (:ids)',
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::BINARY]
        );

        $usedAsDefault = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT payment_method_id FROM sales_channel WHERE payment_method_id IN (:ids)',
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::BINARY]
        );

        $referencedHex = [];
        foreach (array_merge($usedInOrders, $usedAsDefault) as $binaryId) {
            $referencedHex[] = Uuid::fromBytesToHex($binaryId);
        }

        return array_values(array_unique($referencedHex));
    }

    /**
     * @param string[] $paymentMethodIds
     */
    private function detachFromSalesChannels(array $paymentMethodIds): void
    {
        $binaryIds = [];
        foreach ($paymentMethodIds as $paymentMethodId) {
            $binaryIds[] = Uuid::fromHexToBytes($paymentMethodId);
        }

        $this->connection->executeStatement(
            'DELETE FROM sales_channel_payment_method WHERE payment_method_id IN (:ids)',
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
