<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

class LineItemExtraData
{
    /**
     * @var string
     */
    private $sku;

    /**
     * @var null|string
     */
    private $imageUrl;

    /**
     * @var null|string
     */
    private $productUrl;

    public function __construct(string $sku, ?string $imageUrl, ?string $productUrl)
    {
        $this->sku = $sku;
        $this->imageUrl = $imageUrl;
        $this->productUrl = $productUrl;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): void
    {
        $this->imageUrl = $imageUrl;
    }

    public function getProductUrl(): ?string
    {
        return $this->productUrl;
    }

    public function setProductUrl(string $productUrl): void
    {
        $this->productUrl = $productUrl;
    }
}
