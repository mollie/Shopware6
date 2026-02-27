<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\Product;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\VoucherCategory;
use Mollie\Shopware\Component\Mollie\VoucherCategoryCollection;
use Shopware\Core\Framework\Struct\Struct;

final class Product extends Struct
{
    private VoucherCategoryCollection $voucherCategories;
    private bool $isSubscription = false;
    private Interval $interval;

    private int $repetition = 0;

    public function __construct()
    {
        $this->voucherCategories = new VoucherCategoryCollection();
    }

    public function getVoucherCategories(): VoucherCategoryCollection
    {
        return $this->voucherCategories;
    }

    public function setVoucherCategories(VoucherCategoryCollection $voucherCategories): void
    {
        $this->voucherCategories = $voucherCategories;
    }

    public function isSubscription(): bool
    {
        return $this->isSubscription;
    }

    public function setIsSubscription(bool $isSubscription): void
    {
        $this->isSubscription = $isSubscription;
    }

    public function getInterval(): Interval
    {
        return $this->interval;
    }

    public function setInterval(Interval $interval): void
    {
        $this->interval = $interval;
    }

    public function getRepetition(): int
    {
        return $this->repetition;
    }

    public function setRepetition(int $repetition): void
    {
        $this->repetition = $repetition;
    }

    /**
     * @param array<mixed> $customFields
     */
    public static function createFromCustomFields(array $customFields): Product
    {
        $productExtension = new Product();
        $voucherTypes = $customFields['mollie_payments_product_voucher_type'] ?? null;

        if ($voucherTypes !== null) {
            if (! is_array($voucherTypes)) {
                $voucherTypes = [$voucherTypes];
            }
            $collection = new VoucherCategoryCollection();
            foreach ($voucherTypes as $voucherType) {
                $voucher = VoucherCategory::tryFromNumber((int) $voucherType);
                if ($voucher === null) {
                    continue;
                }
                $collection->add($voucher);
            }

            if ($collection->count() > 0) {
                $productExtension->setVoucherCategories($collection);
            }
        }

        $subscriptionEnabled = $customFields['mollie_payments_product_subscription_enabled'] ?? false;
        $subscriptionInterval = $customFields['mollie_payments_product_subscription_interval'] ?? 0;
        $subscriptionUnit = $customFields['mollie_payments_product_subscription_interval_unit'] ?? '';
        if ((bool) $subscriptionEnabled && (int) $subscriptionInterval > 0 && mb_strlen($subscriptionUnit) > 0) {
            $subscriptionRepetitions = $customFields['mollie_payments_product_subscription_repetition'] ?? 0;
            $productExtension->setIsSubscription(true);
            $productExtension->setInterval(new Interval($subscriptionInterval,IntervalUnit::from($subscriptionUnit)));
            $productExtension->setRepetition((int) $subscriptionRepetitions);
        }

        return $productExtension;
    }
}
