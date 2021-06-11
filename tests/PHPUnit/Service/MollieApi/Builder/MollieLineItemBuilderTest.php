<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Service\MollieApi\Builder\MollieLineItemBuilder;
use PHPUnit\Framework\TestCase;

class MollieLineItemBuilderTest extends TestCase
{

    public function testConstants(): void
    {
        self::assertSame('customized-products', MollieLineItemBuilder::LINE_ITEM_TYPE_CUSTOM_PRODUCTS);
    }

    public function testBuildLineItems(): void
    {

    }
}
