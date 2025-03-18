<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

use Shopware\Core\Framework\Struct\Struct;

class MollieLineItem extends Struct
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var LineItemPriceStruct
     */
    private $price;

    /**
     * @var string
     */
    private $lineItemId;

    /**
     * @var string
     */
    private $sku;

    /**
     * @var string
     */
    private $imageUrl;

    /**
     * @var string
     */
    private $productUrl;

    /**
     * @var array<mixed>
     */
    private $metaData;

    public function __construct(
        string $type,
        string $name,
        int $quantity,
        LineItemPriceStruct $price,
        string $lineItemId,
        string $sku,
        string $imageUrl,
        string $productUrl
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->lineItemId = $lineItemId;
        $this->sku = $sku;
        $this->imageUrl = $imageUrl;
        $this->productUrl = $productUrl;

        $this->metaData = [];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPrice(): LineItemPriceStruct
    {
        return $this->price;
    }

    public function setPrice(LineItemPriceStruct $price): void
    {
        $this->price = $price;
    }

    public function getLineItemId(): string
    {
        return $this->lineItemId;
    }

    public function setLineItemId(string $lineItemId): void
    {
        $this->lineItemId = $lineItemId;
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

    public function hasRoundingRest(): bool
    {
        return $this->price->getRoundingRest() !== 0.0;
    }

    /**
     * @return void
     */
    public function addMetaData(string $key, string $value)
    {
        $this->metaData[$key] = $value;
    }

    /**
     * @return array|mixed[]
     */
    public function getMetaData(): array
    {
        return $this->metaData;
    }
}
