<?php declare(strict_types=1);

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

    /**
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @param string $sku
     */
    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    /**
     * @return null|string
     */
    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    /**
     * @param string $imageUrl
     */
    public function setImageUrl(string $imageUrl): void
    {
        $this->imageUrl = $imageUrl;
    }

    /**
     * @return null|string
     */
    public function getProductUrl(): ?string
    {
        return $this->productUrl;
    }

    /**
     * @param string $productUrl
     */
    public function setProductUrl(string $productUrl): void
    {
        $this->productUrl = $productUrl;
    }
}
