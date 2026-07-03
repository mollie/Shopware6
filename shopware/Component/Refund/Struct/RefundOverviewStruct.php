<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Struct;

use Mollie\Shopware\Component\Mollie\RefundCollection;
use Shopware\Core\Framework\Struct\Struct;

final class RefundOverviewStruct extends Struct
{
    protected RefundTotalsStruct $totals;
    protected CartStruct $cart;

    protected RefundCollection $refunds;

    public function __construct()
    {
        $this->totals = new RefundTotalsStruct();
        $this->cart = new CartStruct();
        $this->refunds = new RefundCollection();
    }

    public function getApiAlias(): string
    {
        return 'mollie_refund_overview';
    }

    public function getTotals(): RefundTotalsStruct
    {
        return $this->totals;
    }

    public function setTotals(RefundTotalsStruct $totals): void
    {
        $this->totals = $totals;
    }

    public function getCart(): CartStruct
    {
        return $this->cart;
    }

    public function setCart(CartStruct $cart): void
    {
        $this->cart = $cart;
    }

    public function getRefunds(): RefundCollection
    {
        return $this->refunds;
    }

    public function setRefunds(RefundCollection $refunds): void
    {
        $this->refunds = $refunds;
    }
}
