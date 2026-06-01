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
use Shopware\Core\System\Currency\CurrencyEntity;

final class LineItem implements \JsonSerializable
{

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

    /** @var array<string, mixed> */
    private array $metadata = [];

    private string $id = '';

    private int $quantityShipped = 0;
    private ?Money $amountShipped = null;
    private int $quantityRefunded = 0;
    private ?Money $amountRefunded = null;
    private int $quantityCanceled = 0;
    private ?Money $amountCanceled = null;
    private int $shippableQuantity = 0;
    private int $refundableQuantity = 0;
    private int $cancelableQuantity = 0;

    public function __construct(private readonly string $description, private int $quantity, private Money $unitPrice, private Money $amount)
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
        $lineItem->setShopwareLineItemId($delivery->getId());

        $customFields = $delivery->getCustomFields() ?? [];
        $mollieLineId = ($customFields[Mollie::EXTENSION] ?? [])['order_line_id'] ?? null;
        if ($mollieLineId !== null) {
            $lineItem->setId((string) $mollieLineId);
        }

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

        $lineItem->setShopwareLineItemId($orderLineItem->getId());

        $customFields = $orderLineItem->getCustomFields() ?? [];
        $mollieLineId = ($customFields[Mollie::EXTENSION] ?? [])['order_line_id'] ?? null;
        if ($mollieLineId !== null) {
            $lineItem->setId((string) $mollieLineId);
        }

        return $lineItem;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $unitPrice = new Money(
            (float) ($body['unitPrice']['value'] ?? 0),
            (string) ($body['unitPrice']['currency'] ?? ''),
        );
        $amount = new Money(
            (float) ($body['totalAmount']['value'] ?? 0),
            (string) ($body['totalAmount']['currency'] ?? ''),
        );

        $lineItem = new self(
            (string) ($body['name'] ?? ''),
            (int) ($body['quantity'] ?? 1),
            $unitPrice,
            $amount,
        );

        $lineItem->setId((string) ($body['id'] ?? ''));
        $lineItem->setSku((string) ($body['sku'] ?? ''));

        $rawMetadata = $body['metadata'] ?? [];
        $metadata = is_string($rawMetadata) ? (json_decode($rawMetadata, true) ?? []) : $rawMetadata;
        $shopwareLineItemId = (string) ($metadata['orderLineItemId'] ?? '');
        if ($shopwareLineItemId !== '') {
            $lineItem->setShopwareLineItemId($shopwareLineItemId);
        }

        $lineItem->setQuantityShipped((int) ($body['quantityShipped'] ?? 0));
        $lineItem->setQuantityRefunded((int) ($body['quantityRefunded'] ?? 0));
        $lineItem->setQuantityCanceled((int) ($body['quantityCanceled'] ?? 0));
        $lineItem->setShippableQuantity((int) ($body['shippableQuantity'] ?? 0));
        $lineItem->setRefundableQuantity((int) ($body['refundableQuantity'] ?? 0));
        $lineItem->setCancelableQuantity((int) ($body['cancelableQuantity'] ?? 0));

        if (isset($body['amountShipped']['value'], $body['amountShipped']['currency'])) {
            $lineItem->setAmountShipped(new Money((float) $body['amountShipped']['value'], (string) $body['amountShipped']['currency']));
        }
        if (isset($body['amountRefunded']['value'], $body['amountRefunded']['currency'])) {
            $lineItem->setAmountRefunded(new Money((float) $body['amountRefunded']['value'], (string) $body['amountRefunded']['currency']));
        }
        if (isset($body['amountCanceled']['value'], $body['amountCanceled']['currency'])) {
            $lineItem->setAmountCanceled(new Money((float) $body['amountCanceled']['value'], (string) $body['amountCanceled']['currency']));
        }

        return $lineItem;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
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

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function setUnitPrice(Money $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function setAmount(Money $amount): void
    {
        $this->amount = $amount;
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

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        $vars['totalAmount'] = $vars['amount'];
        unset($vars['amount']);

        if (count($vars['metadata']) > 0) {
            $vars['metadata'] = json_encode($vars['metadata']);
        }

        return array_filter($vars, function ($value) {
            if (is_array($value)) {
                return count($value) > 0;
            }
            return $value !== null;
        });
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getShopwareLineItemId(): string
    {
        return (string) ($this->metadata['orderLineItemId'] ?? '');
    }

    public function setShopwareLineItemId(string $id): void
    {
        $this->metadata['orderLineItemId'] = $id;
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

    public function getQuantityShipped(): int
    {
        return $this->quantityShipped;
    }

    public function setQuantityShipped(int $quantityShipped): void
    {
        $this->quantityShipped = $quantityShipped;
    }

    public function getAmountShipped(): ?Money
    {
        return $this->amountShipped;
    }

    public function setAmountShipped(Money $amountShipped): void
    {
        $this->amountShipped = $amountShipped;
    }

    public function getQuantityRefunded(): int
    {
        return $this->quantityRefunded;
    }

    public function setQuantityRefunded(int $quantityRefunded): void
    {
        $this->quantityRefunded = $quantityRefunded;
    }

    public function getAmountRefunded(): ?Money
    {
        return $this->amountRefunded;
    }

    public function setAmountRefunded(Money $amountRefunded): void
    {
        $this->amountRefunded = $amountRefunded;
    }

    public function getQuantityCanceled(): int
    {
        return $this->quantityCanceled;
    }

    public function setQuantityCanceled(int $quantityCanceled): void
    {
        $this->quantityCanceled = $quantityCanceled;
    }

    public function getAmountCanceled(): ?Money
    {
        return $this->amountCanceled;
    }

    public function setAmountCanceled(Money $amountCanceled): void
    {
        $this->amountCanceled = $amountCanceled;
    }

    public function getShippableQuantity(): int
    {
        return $this->shippableQuantity;
    }

    public function setShippableQuantity(int $shippableQuantity): void
    {
        $this->shippableQuantity = $shippableQuantity;
    }

    public function getRefundableQuantity(): int
    {
        return $this->refundableQuantity;
    }

    public function setRefundableQuantity(int $refundableQuantity): void
    {
        $this->refundableQuantity = $refundableQuantity;
    }

    public function getCancelableQuantity(): int
    {
        return $this->cancelableQuantity;
    }

    public function setCancelableQuantity(int $cancelableQuantity): void
    {
        $this->cancelableQuantity = $cancelableQuantity;
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
