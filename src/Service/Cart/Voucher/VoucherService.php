<?php

namespace Kiener\MolliePayments\Service\Cart\Voucher;

use Kiener\MolliePayments\Repository\Product\ProductRepositoryInterface;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Kiener\MolliePayments\Struct\Product\ProductAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\Exception\ProductNumberNotFoundException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VoucherService
{

    /**
     * @var ProductRepositoryInterface
     */
    private $repoProducts;


    /**
     * @param ProductRepositoryInterface $repoProducts
     */
    public function __construct(ProductRepositoryInterface $repoProducts)
    {
        $this->repoProducts = $repoProducts;
    }


    /**
     * @param LineItem $item
     * @param SalesChannelContext $context
     * @return string
     */
    public function getFinalVoucherType(LineItem $item, SalesChannelContext $context): string
    {
        $attributes = new LineItemAttributes($item);

        # verify if we even have a product number
        if (empty($attributes->getProductNumber())) {
            return VoucherType::TYPE_NOTSET;
        }

        # also make sure to avoid invalid product numbers
        # such as with custom products
        if (trim($attributes->getProductNumber()) === '*') {
            return VoucherType::TYPE_NOTSET;
        }

        $currentProduct = $this->getProductByNumber($attributes->getProductNumber(), $context);
        $currentAttributes = new ProductAttributes($currentProduct);

        $voucherType = $currentAttributes->getVoucherType();

        # if we don't have a voucher type in our current product,
        # but we do have a parent product, then check if that one is a voucher.
        if ($voucherType === VoucherType::TYPE_NOTSET && !empty($currentProduct->getParentId())) {

            $parentProduct = $this->getProductById($currentProduct->getParentId(), $context);
            $parentAttributes = new ProductAttributes($parentProduct);

            $voucherType = $parentAttributes->getVoucherType();
        }

        return $voucherType;
    }

    /**
     * @param PaymentMethodEntity $pm
     * @return bool
     */
    public function isVoucherPaymentMethod(PaymentMethodEntity $pm): bool
    {
        $attributes = new PaymentMethodAttributes($pm);

        if ($attributes->isVoucherMethod()) {
            return true;
        }

        return false;
    }

    /**
     * @param string $productNumber
     * @param SalesChannelContext $context
     * @return ProductEntity
     */
    private function getProductByNumber(string $productNumber, SalesChannelContext $context): ProductEntity
    {
        $products = $this->repoProducts->findByNumber($productNumber, $context);

        if (count($products) <= 0) {
            throw new ProductNumberNotFoundException($productNumber);
        }

        return array_shift($products);
    }

    /**
     * @param string $productId
     * @param SalesChannelContext $context
     * @return ProductEntity
     */
    private function getProductById(string $productId, SalesChannelContext $context): ProductEntity
    {
        $products = $this->repoProducts->findByID($productId, $context);

        if (count($products) <= 0) {
            throw new ProductNotFoundException($productId);
        }

        return array_shift($products);
    }

}
