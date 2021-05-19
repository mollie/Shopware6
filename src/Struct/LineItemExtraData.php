<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

class LineItemExtraData
{
    /**
     * @var string|null
     */
    private $sku;

    /**
     * @var string|null
     */
    private $imageUrl;

    /**
     * @var string|null
     */
    private $productUrl;

    /**
     * @return string
     */
    public function getSku(): ?string
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
     * @return string
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
     * @return string
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
