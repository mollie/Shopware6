<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Mollie\Exception\MissingLineItemPriceException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\System\Currency\CurrencyEntity;

final class LineItem implements \JsonSerializable
{
    use JsonSerializableTrait;

    private LineItemType $type;

    private string $quantityUnit;
    private Money $discountAmount;

    private string $vatRate;
    private Money $vatAmount;
    private string $sku;
    private string $imageUrl;
    private string $productUrl;

    /**
     * @var VoucherCategory[]
     */
    private array $categories;

    public function __construct(private string $description, private int $quantity, private Money $unitPrice, private Money $totalAmount)
    {
        $this->type = LineItemType::PHYSICAL;
    }

    public static function fromDelivery(OrderDeliveryEntity $delivery, CurrencyEntity $currency): self
    {
        $shippingMethod = $delivery->getShippingMethod();
        if (! $shippingMethod instanceof ShippingMethodEntity) {
            throw new \Exception('Shipping method is not exists');
        }
        $shippingCosts = $delivery->getShippingCosts();

        /** @var CalculatedTax $calculatedTax */
        $calculatedTax = $shippingCosts->getCalculatedTaxes()->first();

        if (! $calculatedTax instanceof CalculatedTax) {
            throw new \Exception('Shipping costs not exists');
        }

        $lineItem = new self((string) $shippingMethod->getName(), $shippingCosts->getQuantity(), new Money($shippingCosts->getUnitPrice(), $currency->getIsoCode()), new Money($shippingCosts->getTotalPrice(), $currency->getIsoCode()));
        $lineItem->setType(LineItemType::SHIPPING);
        $lineItem->setVatAmount(new Money($calculatedTax->getTax(), $currency->getIsoCode()));
        $lineItem->setVatRate((string) $calculatedTax->getTaxRate());
        $lineItem->setSku(sprintf('mol-delivery-%s', $shippingMethod->getId()));

        return $lineItem;
    }

    public static function fromOrderLine(OrderLineItemEntity $orderLineItem, CurrencyEntity $currency): self
    {
        $sku = $orderLineItem->getId();

        $product = $orderLineItem->getProduct();
        $linItemPrice = $orderLineItem->getPrice();
        if (! $linItemPrice instanceof CalculatedPrice) {
            throw new MissingLineItemPriceException($orderLineItem->getLabel());
        }
        /** @var CalculatedTax $taxes */
        $taxes = $linItemPrice->getCalculatedTaxes()->first();

        $lineItemType = LineItemType::fromOderLineItem($orderLineItem);
        $lineItem = new self($orderLineItem->getLabel(), $linItemPrice->getQuantity(), new Money($linItemPrice->getUnitPrice(), $currency->getIsoCode()), new Money($linItemPrice->getTotalPrice(), $currency->getIsoCode()));

        $lineItem->setType($lineItemType);
        $lineItem->setSku($sku);

        if ($product instanceof ProductEntity) {
            $voucherCategories = $product->getCustomFields()['mollie_payments_product_voucher_type'] ?? null;

            if ($voucherCategories !== null) {
                if (! is_array($voucherCategories)) {
                    $voucherCategories = [$voucherCategories];
                }
                foreach ($voucherCategories as $voucherCategoryValue) {
                    $voucherCategory = VoucherCategory::tryFromNumber((int) $voucherCategoryValue);
                    if (! $voucherCategory instanceof VoucherCategory) {
                        continue;
                    }
                    $lineItem->addCategory($voucherCategory);
                }
            }

            $lineItem->setSku($product->getProductNumber());
        }

        $lineItem->setVatAmount(new Money($taxes->getTax(), $currency->getIsoCode()));
        $lineItem->setVatRate((string) $taxes->getTaxRate());

        return $lineItem;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function getTotalAmount(): Money
    {
        return $this->totalAmount;
    }

    public function getType(): LineItemType
    {
        return $this->type;
    }

    public function setType(LineItemType $type): void
    {
        $this->type = $type;
    }

    public function getQuantityUnit(): string
    {
        return $this->quantityUnit;
    }

    public function setQuantityUnit(string $quantityUnit): void
    {
        $this->quantityUnit = $quantityUnit;
    }

    public function getDiscountAmount(): Money
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(Money $discountAmount): void
    {
        $this->discountAmount = $discountAmount;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): void
    {
        $this->vatRate = $vatRate;
    }

    public function getVatAmount(): Money
    {
        return $this->vatAmount;
    }

    public function setVatAmount(Money $vatAmount): void
    {
        $this->vatAmount = $vatAmount;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): void
    {
        $this->imageUrl = $imageUrl;
    }

    public function getProductUrl(): string
    {
        return $this->productUrl;
    }

    /**
     * @return VoucherCategory[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function setProductUrl(string $productUrl): void
    {
        $this->productUrl = $productUrl;
    }

    private function addCategory(VoucherCategory $voucherCategory): void
    {
        $this->categories[] = $voucherCategory;
    }
}
