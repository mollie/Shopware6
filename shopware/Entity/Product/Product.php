<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\Product;

use Mollie\Shopware\Component\Mollie\VoucherCategory;
use Mollie\Shopware\Component\Mollie\VoucherCategoryCollection;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\Struct;

final class Product extends Struct
{
    private VoucherCategoryCollection $voucherCategories;

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

    /**
     * @param LineItem|OrderLineItemEntity|ProductEntity $product
     * @param array<mixed> $customFields
     *
     * @return LineItem|OrderLineItemEntity|ProductEntity
     */
    public static function setFromCustomFields(Struct $product,array $customFields): Struct
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
                $product->addExtension(Mollie::EXTENSION, $productExtension);
            }
        }

        return $product;
    }
}
