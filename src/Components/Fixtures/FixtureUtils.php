<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures;

use Kiener\MolliePayments\Components\Fixtures\Utils\MediaUtils;
use Kiener\MolliePayments\Components\Fixtures\Utils\SalesChannelUtils;
use Kiener\MolliePayments\Components\Fixtures\Utils\TaxUtils;

class FixtureUtils
{
    private MediaUtils $mediaUtils;
    private TaxUtils $taxUtils;
    private SalesChannelUtils $salesChannelUtils;

    public function __construct(MediaUtils $mediaUtils, TaxUtils $taxUtils, SalesChannelUtils $salesChannelUtils)
    {
        $this->mediaUtils = $mediaUtils;
        $this->taxUtils = $taxUtils;
        $this->salesChannelUtils = $salesChannelUtils;
    }

    public function getMedia(): MediaUtils
    {
        return $this->mediaUtils;
    }

    public function getTaxes(): TaxUtils
    {
        return $this->taxUtils;
    }

    public function getSalesChannels(): SalesChannelUtils
    {
        return $this->salesChannelUtils;
    }
}
