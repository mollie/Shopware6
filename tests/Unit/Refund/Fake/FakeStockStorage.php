<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Fake;

use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeStockStorage extends AbstractStockStorage
{
    /** @var list<StockAlteration> */
    public array $alterations = [];

    public function getDecorated(): AbstractStockStorage
    {
        return $this;
    }

    public function load(StockLoadRequest $stockRequest, SalesChannelContext $context): StockDataCollection
    {
        return new StockDataCollection([]);
    }

    public function alter(array $changes, Context $context): void
    {
        foreach ($changes as $change) {
            $this->alterations[] = $change;
        }
    }

    public function index(array $productIds, Context $context): void
    {
    }
}
