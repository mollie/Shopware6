<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\PriceDrift;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Marks active subscriptions as "dirty" (price re-check required) when a product
 * or shipping price changes, so the expensive cart recalculation in
 * PriceDriftDetector only runs for affected subscriptions instead of all of them.
 *
 * Two queries per call: a SELECT that joins the subscription's order to the
 * changed product / shipping method (giving us the affected subscription ids and
 * their sales channel), then a single UPDATE for the ones whose sales channel has
 * auto price update enabled. In "keep" mode the detector never processes them, so
 * flagging there would leave a dirty state that is never cleared.
 */
final class SubscriptionPriceCheckFlagger implements SubscriptionPriceCheckFlaggerInterface
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function flagByProductIds(array $productIds): int
    {
        $literals = $this->binaryLiterals($productIds);
        if ($literals === '') {
            return 0;
        }

        return $this->selectAndFlag(sprintf(
            'SELECT DISTINCT LOWER(HEX(`s`.`id`)) AS `id`, LOWER(HEX(`s`.`sales_channel_id`)) AS `salesChannelId`
             FROM `mollie_subscription` `s`
             INNER JOIN `order_line_item` `li`
                 ON `li`.`order_id` = `s`.`order_id` AND `li`.`order_version_id` = `s`.`order_version_id`
             WHERE %s AND `li`.`product_id` IN (%s)',
            $this->activeCandidateCondition(),
            $literals
        ));
    }

    public function flagByShippingMethodIds(array $shippingMethodIds): int
    {
        $literals = $this->binaryLiterals($shippingMethodIds);
        if ($literals === '') {
            return 0;
        }

        return $this->selectAndFlag(sprintf(
            'SELECT DISTINCT LOWER(HEX(`s`.`id`)) AS `id`, LOWER(HEX(`s`.`sales_channel_id`)) AS `salesChannelId`
             FROM `mollie_subscription` `s`
             INNER JOIN `order_delivery` `d`
                 ON `d`.`order_id` = `s`.`order_id` AND `d`.`order_version_id` = `s`.`order_version_id`
             WHERE %s AND `d`.`shipping_method_id` IN (%s)',
            $this->activeCandidateCondition(),
            $literals
        ));
    }

    /**
     * Shared WHERE for "an active, non-cancelled subscription that is not already
     * flagged/notified". Uses literal status values mirroring the constants.
     */
    private function activeCandidateCondition(): string
    {
        return sprintf(
            "`s`.`status` IN ('%s','%s') AND `s`.`canceled_at` IS NULL AND `s`.`price_update_state` = '%s'",
            SubscriptionStatus::ACTIVE->value,
            SubscriptionStatus::RESUMED->value,
            PriceDriftDetector::STATE_NONE
        );
    }

    private function selectAndFlag(string $selectSql): int
    {
        // Runs inside the product/shipping save request — a failure here must
        // never break that save, so swallow and log any error.
        try {
            $this->logger->debug('Selecting subscriptions affected by a price change', ['sql' => $selectSql]);

            /** @var array<int,array{id:string,salesChannelId:string}> $candidates */
            $candidates = $this->connection->fetchAllAssociative($selectSql);
            if (count($candidates) === 0) {
                return 0;
            }

            $literals = [];
            $skippedCount = 0;
            $autoMode = [];

            foreach ($candidates as $candidate) {
                $salesChannelId = $candidate['salesChannelId'];
                if (! isset($autoMode[$salesChannelId])) {
                    $settings = $this->settingsService->getSubscriptionSettings($salesChannelId);
                    $autoMode[$salesChannelId] = $settings->isEnabled() && $settings->isAutoPriceUpdate();
                }

                if ($autoMode[$salesChannelId] === false) {
                    ++$skippedCount;
                    continue;
                }

                $literals[] = "X'" . $candidate['id'] . "'";
            }

            if ($skippedCount > 0) {
                $this->logger->debug(sprintf('%d subscription(s) not flagged for price re-check (price update mode is "keep")', $skippedCount));
            }

            if (count($literals) === 0) {
                return 0;
            }

            $updateSql = sprintf(
                "UPDATE `mollie_subscription`
                 SET `price_update_state` = '%s'
                 WHERE `price_update_state` = '%s' AND `id` IN (%s)",
                PriceDriftDetector::STATE_DIRTY,
                PriceDriftDetector::STATE_NONE,
                implode(',', $literals)
            );

            $this->logger->info('Flagging subscriptions for a price re-check', ['sql' => $updateSql]);

            return (int) $this->connection->executeStatement($updateSql);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to flag subscriptions for a price re-check', [
                'exception' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Turns hex uuids into a comma-separated list of binary SQL literals
     * (X'...'), dropping anything that is not a valid uuid (so the values are
     * safe to inline). Returns '' when nothing valid remains.
     *
     * @param string[] $hexIds
     */
    private function binaryLiterals(array $hexIds): string
    {
        $literals = [];
        foreach (array_unique($hexIds) as $hexId) {
            if (Uuid::isValid($hexId)) {
                $literals[] = "X'" . $hexId . "'";
            }
        }

        return implode(',', $literals);
    }
}
