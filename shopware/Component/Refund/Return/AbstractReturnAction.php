<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Return;

use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnStruct;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * What every action on a return of the Return Management has to do before it can touch a refund:
 * find the return, find its order, and check that the merchant did not switch the integration off.
 *
 * The actions are reached through OrderReturnSubscriber, one per state the return enters.
 */
abstract class AbstractReturnAction
{
    public function __construct(
        #[Autowire(service: OrderReturnLoader::class)]
        protected readonly OrderReturnLoaderInterface $orderReturnLoader,
        #[Autowire(service: SettingsService::class)]
        protected readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Loads the return unless the Return Management is missing altogether. The loader already logs
     * a return it cannot find.
     */
    protected function loadReturn(string $returnId, Context $context): ?OrderReturnStruct
    {
        if (! $this->orderReturnLoader->isAvailable()) {
            $this->logger->warning('OrderReturn - Feature disabled (SwagCommercial not installed)', ['returnId' => $returnId]);

            return null;
        }

        return $this->orderReturnLoader->load($returnId, $context);
    }

    /**
     * The order the return belongs to, or null when there is none or the merchant switched the
     * integration off for its sales channel.
     */
    protected function resolveOrder(OrderReturnStruct $orderReturn): ?OrderEntity
    {
        $order = $orderReturn->getOrder();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('OrderReturn - No order associated with return', ['returnId' => $orderReturn->getId()]);

            return null;
        }

        if ($this->isReturnManagementDisabled($order, $this->buildLogData($orderReturn, $order))) {
            return null;
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLogData(OrderReturnStruct $orderReturn, OrderEntity $order): array
    {
        return [
            'returnId' => $orderReturn->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'orderId' => $order->getId(),
        ];
    }

    /**
     * The return is read in the context the transition happened in, so the amounts the merchant just
     * edited are used. Everything that persists has to run against the live version though: the
     * working version of an order is deleted when the merchant saves it, and mollie_refund has an
     * ON DELETE CASCADE on the order version - the refund record would be dropped with it.
     */
    protected function liveContext(Context $context): Context
    {
        if ($context->getVersionId() === Defaults::LIVE_VERSION) {
            return $context;
        }

        return $context->createWithVersionId(Defaults::LIVE_VERSION);
    }

    /**
     * @param array<string, mixed> $logData
     */
    private function isReturnManagementDisabled(OrderEntity $order, array $logData): bool
    {
        $refundSettings = $this->settingsService->getRefundSettings($order->getSalesChannelId());

        if (! $refundSettings->isReturnManagementDisabled()) {
            return false;
        }

        $this->logger->debug('OrderReturn - Return Management integration is disabled in the plugin configuration, skipping', $logData);

        return true;
    }
}
