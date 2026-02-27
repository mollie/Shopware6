<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\MolliePage;

final class PageResponse
{
    public function __construct(
        private string $url,
        private \DOMDocument $dom,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getDom(): \DOMDocument
    {
        return $this->dom;
    }
}
