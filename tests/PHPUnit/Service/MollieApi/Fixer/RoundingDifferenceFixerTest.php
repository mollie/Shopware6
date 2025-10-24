<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Fixer;

use Kiener\MolliePayments\Service\MollieApi\Fixer\RoundingDifferenceFixer;
use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use Mollie\Api\Types\OrderLineType;
use PHPUnit\Framework\TestCase;

class RoundingDifferenceFixerTest extends TestCase
{
    /**
     * @var RoundingDifferenceFixer
     */
    private $fixer;

    /**
     * @var float
     */
    private $orderTotal;

    /**
     * @var MollieLineItemCollection
     */
    private $cartItems;

    protected function setUp(): void
    {
        $this->fixer = new RoundingDifferenceFixer();

        $this->orderTotal = 14.81;

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
     * This test verifies that no item is added, if we
     * tell the fixer that the order amount is "correct".
     */
    public function testNoItemAddedIfNoDiff(): void
    {
        $newLines = $this->fixer->fixAmountDiff($this->orderTotal, $this->cartItems, '', '');

        $this->assertCount(4, $newLines);
    }

    /**
     * This test verifies that a new item is added if we
     * have found a difference in the values.
     */
    public function testItemAddedIfDiff(): void
    {
        $newLines = $this->fixer->fixAmountDiff($this->orderTotal + 0.5, $this->cartItems, '', '');

        $this->assertCount(5, $newLines);
    }

    /**
     * This test verifies that the new sum of the cart amount is the
     * one that we told the fixe when running it.
     * So we add a diff, and then make sure the sum of all items is correct.
     *
     * @testWith       [  0.50 ]
     *                 [ -0.50 ]
     *                 [  0.01 ]
     *                 [ -0.01 ]
     */
    public function testDiffItemLeadsToCorrectItemSum(float $diff): void
    {
        $newLines = $this->fixer->fixAmountDiff($this->orderTotal + $diff, $this->cartItems, '', '');

        $this->assertEquals($this->orderTotal + $diff, $newLines->getCartTotalAmount());
    }

    /**
     * This test verifies the basic properties and structure of
     * our diff-line-item that is built.
     */
    public function testDiffItemProperties(): void
    {
        $diff = 0.5;

        $newLines = $this->fixer->fixAmountDiff($this->orderTotal + $diff, $this->cartItems, 'My DIFF', 'sku-123');

        $diffItem = $newLines->last();

        $this->assertEquals('My DIFF', $diffItem->getName());
        $this->assertEquals(OrderLineType::TYPE_PHYSICAL, $diffItem->getType());

        $this->assertEquals(1, $diffItem->getQuantity());

        $this->assertEquals($diff, $diffItem->getPrice()->getUnitPrice());
        $this->assertEquals($diff, $diffItem->getPrice()->getTotalAmount());

        $this->assertEquals(0, $diffItem->getPrice()->getVatRate(), 'No taxes are allowed for rounding-diff items!');
        $this->assertEquals(0, $diffItem->getPrice()->getVatAmount(), 'No taxes are allowed for rounding-diff items!');

        $this->assertEquals('sku-123', $diffItem->getSku());
        $this->assertEquals('', $diffItem->getImageUrl());
        $this->assertEquals('', $diffItem->getProductUrl());
        $this->assertEquals('', $diffItem->getLineItemId());

        // we need a custom type to verify this type of item later in the refund manager
        $this->assertEquals(['type' => 'rounding'], $diffItem->getMetaData());
    }

    /**
     * This test verifies that we get the correct default name of
     * our diff line item, if we didn't provide a specific one.
     */
    public function testDiffItemDefaultName(): void
    {
        $newLines = $this->fixer->fixAmountDiff($this->orderTotal + 1, $this->cartItems, '', '');

        $diffItem = $newLines->last();

        $this->assertEquals('Automatic Rounding Difference', $diffItem->getName());
    }

    /**
     * This test verifies that we get the correct default sku of
     * our diff line item, if we didn't provide a specific one.
     */
    public function testDiffItemDefaultSKU(): void
    {
        $newLines = $this->fixer->fixAmountDiff($this->orderTotal + 1, $this->cartItems, '', '');

        $diffItem = $newLines->last();

        $this->assertEquals('', $diffItem->getSku());
    }
}
