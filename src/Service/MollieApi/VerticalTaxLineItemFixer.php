<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VerticalTaxLineItemFixer
{
    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param MollieLineItemCollection $lineItems
     * @param SalesChannelContext $salesChannelContext
     */
    public function fixLineItems(MollieLineItemCollection $lineItems, SalesChannelContext $salesChannelContext): void
    {
        $this->logger->debug(
            sprintf('Entering vertical tax calculation fix algorithm')
        );

        $roundingRestSum = $lineItems->getRoundingRestSum();

        if ($roundingRestSum === 0.0) {
            return;
        }

        $productLineItems = $lineItems->filterByProductType();

        $filteredRoundingLineItems = $productLineItems->filterByRoundingRest();

        $firstLineItem = $filteredRoundingLineItems->first();

        if (!$firstLineItem instanceof MollieLineItem) {

            $this->logger->critical('Got a rounding rest but cannot filter for items !');

            throw new \RuntimeException('Got a rounding rest but cannot filter for items !');
        }

        $priceStruct = $firstLineItem->getPrice();
        $vatRate = $priceStruct->getVatRate();
        $fixedPrice = $priceStruct->getTotalAmount() + round($roundingRestSum, MollieOrderPriceBuilder::MOLLIE_PRICE_PRECISION);
        $taxAmount = ($vatRate * $fixedPrice) / (100 + $vatRate);
        $fixedPriceStruct = new LineItemPriceStruct($priceStruct->getUnitPrice(), $fixedPrice, $taxAmount, $vatRate);

        $firstLineItem->setPrice($fixedPriceStruct);
    }

}
