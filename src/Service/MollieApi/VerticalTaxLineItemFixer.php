<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use Monolog\Logger;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VerticalTaxLineItemFixer
{
    /**
     * @var LoggerService
     */
    private $logger;

    public function __construct(LoggerService $logger)
    {

        $this->logger = $logger;
    }

    public function fixLineItems(MollieLineItemCollection $lineItems, SalesChannelContext $salesChannelContext): void
    {
        $this->logger->addDebugEntry(
            sprintf('Entering vertical tax calculation fix algorithm'),
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getContext()
        );

        $roundingRestSum = $lineItems->getRoundingRestSum();

        if ($roundingRestSum === 0.0) {
            return;
        }

        $productLineItems = $lineItems->filterByProductType();

        $filteredRoundingLineItems = $productLineItems->filterByRoundingRest();

        $firstLineItem = $filteredRoundingLineItems->first();

        if (!$firstLineItem instanceof MollieLineItem) {
            $this->logger->addEntry(
                'Got a rounding rest but cannot filter for items !',
                $salesChannelContext->getContext(),
                null,
                null,
                Logger::CRITICAL
            );

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
