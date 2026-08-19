<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SessionBuilderInterface
{
    public function buildFromProduct(SalesChannelProductEntity $product, SalesChannelContext $salesChannelContext): Session;
}
