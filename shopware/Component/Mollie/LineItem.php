<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Mollie\Exception\MissingLineItemPriceException;
use Mollie\Shopware\Component\Mollie\Exception\MissingShippingMethodException;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
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

    public function __construct(private readonly string $description, private readonly int $quantity, private readonly Money $unitPrice, private readonly Money $totalAmount)
    {
        $this->type = LineItemType::PHYSICAL;
    }

    public static function fromDelivery(OrderDeliveryEntity $delivery, CurrencyEntity $currency, string $taxStatus = CartPrice::TAX_STATE_GROSS): self
    {
        $shippingMethod = $delivery->getShippingMethod();
        if (! $shippingMethod instanceof ShippingMethodEntity) {
            throw new MissingShippingMethodException();
        }
        $shippingCosts = $delivery->getShippingCosts();

        $lineItem = self::createBaseLineItem((string) $shippingMethod->getName(), $taxStatus, $shippingCosts, $currency);
        $lineItem->setType(LineItemType::SHIPPING);
        $lineItem->setSku(sprintf('mol-delivery-%s', $shippingMethod->getId()));

        return $lineItem;
    }

    public static function fromOrderLine(OrderLineItemEntity $orderLineItem, CurrencyEntity $currency, string $taxStatus = CartPrice::TAX_STATE_GROSS): self
    {
        $sku = $orderLineItem->getId();

        $product = $orderLineItem->getProduct();
        $linItemPrice = $orderLineItem->getPrice();
        if (! $linItemPrice instanceof CalculatedPrice) {
            throw new MissingLineItemPriceException($orderLineItem->getLabel());
        }

        $lineItem = self::createBaseLineItem($orderLineItem->getLabel(), $taxStatus, $linItemPrice, $currency);

        $lineItemType = LineItemType::fromOderLineItem($orderLineItem);

        $lineItem->setType($lineItemType);
        $lineItem->setSku($sku);

        if ($product instanceof ProductEntity) {
            $mollieProduct = $product->getExtension(Mollie::EXTENSION);
            if ($mollieProduct instanceof Product) {
                $voucherCategories = $mollieProduct->getVoucherCategories();
                foreach ($voucherCategories as $voucherCategory) {
                    $lineItem->addCategory($voucherCategory);
                }
            }

            $lineItem->setSku($product->getProductNumber());
        }

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

    private static function createBaseLineItem(string $label, string $taxStatus, CalculatedPrice $price, CurrencyEntity $currency): self
    {
        $tax = self::calculateTax($price->getCalculatedTaxes(), $price->getTotalPrice());

        $unitPrice = new Money($price->getUnitPrice(), $currency->getIsoCode());
        $totalPrice = new Money($price->getTotalPrice(), $currency->getIsoCode());

        if ($taxStatus === CartPrice::TAX_STATE_NET && $tax instanceof CalculatedTax) {
            $unitPrice = new Money($price->getUnitPrice() + $tax->getTax() / $price->getQuantity(), $currency->getIsoCode());
            $totalPrice = new Money($price->getTotalPrice() + $tax->getTax(), $currency->getIsoCode());
        }

        $lineItem = new self($label, $price->getQuantity(), $unitPrice, $totalPrice);

        if ($tax instanceof CalculatedTax) {
            $lineItem->setVatAmount(new Money($tax->getTax(), $currency->getIsoCode()));
            $lineItem->setVatRate((string) $tax->getTaxRate());
        }

        return $lineItem;
    }

    /**
     * Mollie Payments API does allow only one vatRate and vatAmount per line item.
     * In Shopware, the shipping costs and voucher lineitems might have multiple vat rates
     * so we need to create an avarage tax amount and recalculate it for the API
     */
    private static function calculateTax(CalculatedTaxCollection $taxCollection, float $price): ?CalculatedTax
    {
        if ($taxCollection->count() === 0) {
            return null;
        }
        if ($taxCollection->count() === 1) {
            /** @var CalculatedTax $calculatedTax */
            $calculatedTax = $taxCollection->first();
            if ($calculatedTax instanceof CalculatedTax) {
                return $calculatedTax;
            }

            return null;
        }
        $totalAmount = 0.0;
        $totalTaxAmount = 0.0;
        /** @var CalculatedTax $calculatedTax */
        foreach ($taxCollection as $calculatedTax) {
            $totalTaxAmount += $calculatedTax->getTax();
            $totalAmount += $calculatedTax->getPrice();
        }

        $averageVatRate = round($totalTaxAmount / $totalAmount * 100, 2);
        $vatAmount = $price * ($averageVatRate / (100 + $averageVatRate));

        return new CalculatedTax($vatAmount, $averageVatRate, $price);
    }

    private function addCategory(VoucherCategory $voucherCategory): void
    {
        $this->categories[] = $voucherCategory;
    }
}
