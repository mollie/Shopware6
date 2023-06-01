<?php

namespace Kiener\MolliePayments\Service\Cart\Voucher;

use Kiener\MolliePayments\Repository\Product\ProductRepositoryInterface;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Kiener\MolliePayments\Struct\Product\ProductAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param ProductRepositoryInterface $repoProducts
     * @param LoggerInterface $logger
     */
    public function __construct(ProductRepositoryInterface $repoProducts, LoggerInterface $logger)
    {
        $this->repoProducts = $repoProducts;
        $this->logger = $logger;
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

        try {
            # we might not always be able to find a product
            # some plugins (custom products, easycoupon) use not-existing product numbers in the line items.
            # in that case, we just ignore this, and return voucher type NOT_SET (in the exception)
            $currentProduct = $this->getProductByNumber($attributes->getProductNumber(), $context);
        } catch (ProductNumberNotFoundException $ex) {
            $this->logger->notice(
                'VoucherService could not find product: ' . $attributes->getProductNumber() . '. This might be a custom product, or voucher. If so, you can just ignore this message! If not, something is going wrong in here!'
            );

            return VoucherType::TYPE_NOTSET;
        }


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
