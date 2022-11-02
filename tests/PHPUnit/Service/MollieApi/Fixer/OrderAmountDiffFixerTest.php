<?php

namespace MolliePayments\Tests\Service\MollieApi\Fixer;

use Kiener\MolliePayments\Service\MollieApi\Fixer\OrderAmountDiffFixer;
use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use PHPUnit\Framework\TestCase;


class OrderAmountDiffFixerTest extends TestCase
{

    /**
     * @var MollieLineItemCollection
     */
    private $cartItems;


    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->cartItems = new MollieLineItemCollection([
            new MollieLineItem(
                '',
                '',
                1,
                new LineItemPriceStruct(2.73, 2.73, 0.44, 19),
                '',
                '',
                '',
                ''
            ),
            new MollieLineItem(
                '',
                '',
                1,
                new LineItemPriceStruct(2.93, 2.93, 0.47, 19),
                '',
                '',
                '',
                ''
            ),
            new MollieLineItem(
                '',
                '',
                1,
                new LineItemPriceStruct(1.65, 1.65, 0.26, 19),
                '',
                '',
                '',
                ''
            ),
            new MollieLineItem(
                '',
                '',
                1,
                new LineItemPriceStruct(7.5, 7.5, 1.2, 19),
                '',
                '',
                '',
                ''
            ),
        ]);
    }

    /**
     * This test verifies that a small diff of max. 0.01 cent offset, adjusts
     * the first line item, so that the sum of the items match the order sum again.
     * There are combinations in Shopware with multiple decimals on line items, but 2 decimals on total values,
     * that lead to a difference (by design). So we move that small diff cent to the first item.
     * We also test a diff of +0.01 and -0.01 with our test data.
     *
     * @testWith        [0.01]
     *                  [-0.01]
     *
     * @return void
     */
    public function testSmallDiffsAreAddedToFirstItem(float $expectedDiffValue)
    {
        $orderTotal = 14.81 + $expectedDiffValue;

        $fixer = new OrderAmountDiffFixer();
        $newLines = $fixer->fixSmallAmountDiff($orderTotal, $this->cartItems);

        $firstItem = $newLines->getElements()[0];
        $newSum = $this->cartItems->getCartTotalAmount();

        # our new calculated total sum needs to exactly
        # match the sum of our order
        $this->assertEquals($orderTotal, $newSum);

        # our first line item needs to be adjusted correctly.
        # the total sum needs to have the diff!
        $this->assertEquals(2.73 + $expectedDiffValue, $firstItem->getPrice()->getTotalAmount());
        $this->assertEquals(2.73, $firstItem->getPrice()->getUnitPrice());
        $this->assertEquals(0.44, $firstItem->getPrice()->getVatAmount());
        $this->assertEquals(19, $firstItem->getPrice()->getVatRate());
        $this->assertEquals(0, $firstItem->getPrice()->getRoundingRest());
    }

    /**
     * This test verifies that a diff >= 0.02 is NOT changing the line items.
     * If we have such a high diff, then something is wrong, so we must not fix anything.
     *
     * @testWith        [0.02]
     *                  [-0.02]
     *
     * @return void
     */
    public function testLargeDiffsDoNothing(float $expectedDiffValue)
    {
        $orderTotal = 14.81 + $expectedDiffValue;

        $fixer = new OrderAmountDiffFixer();
        $newLines = $fixer->fixSmallAmountDiff($orderTotal, $this->cartItems);

        $newSum = $this->cartItems->getCartTotalAmount();

        $this->assertEquals(14.81, $newSum);
        $this->assertEquals(2.73, $newLines->getElements()[0]->getPrice()->getTotalAmount());
    }

}
