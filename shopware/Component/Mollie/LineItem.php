<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\System\Currency\CurrencyEntity;

final class LineItem implements \JsonSerializable
{
    use JsonSerializableTrait;

    private string $type;

    private string $quantityUnit;
    private Money $discountAmount;

    private string $vatRate;
    private Money $vatAmount;
    private string $sku;
    private string $imageUrl;
    private string $productUrl;

    public function __construct(private string $description, private int $quantity, private Money $unitPrice, private Money $totalAmount)
    {
        $this->type = (string) (new LineItemType());
    }

    public static function fromDelivery(OrderDeliveryEntity $delivery, CurrencyEntity $currency): self
    {
        $shippingCosts = $delivery->getShippingCosts();
        $shippingMethod = $delivery->getShippingMethod();
        /** @var CalculatedTax $calculatedTax */
        $calculatedTax = $shippingCosts->getCalculatedTaxes()->first();

        $lineItem = new self($shippingMethod->getName(), $shippingCosts->getQuantity(), new Money($shippingCosts->getUnitPrice(), $currency->getIsoCode()), new Money($shippingCosts->getTotalPrice(), $currency->getIsoCode()));
        $lineItem->setType(new LineItemType(LineItemType::SHIPPING));
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
        /** @var CalculatedTax $taxes */
        $taxes = $linItemPrice->getCalculatedTaxes()->first();

        $lineItemType = LineItemType::fromOderLineItem($orderLineItem);
        $lineItem = new self($orderLineItem->getLabel(), $linItemPrice->getQuantity(), new Money($linItemPrice->getUnitPrice(), $currency->getIsoCode()), new Money($linItemPrice->getTotalPrice(), $currency->getIsoCode()));

        $lineItem->setType($lineItemType);
        $lineItem->setSku($sku);

        if ($product instanceof ProductEntity) {
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
        return new LineItemType($this->type);
    }

    public function setType(LineItemType $type): void
    {
        $this->type = (string) $type;
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

    public function setProductUrl(string $productUrl): void
    {
        $this->productUrl = $productUrl;
    }

    public function toArray(): array
    {
        return json_decode(json_encode($this), true);
    }
}
