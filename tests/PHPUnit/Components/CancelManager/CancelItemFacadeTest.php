<?php
declare(strict_types=1);

namespace PHPUnit\Components\CancelManager;

use MolliePayments\Tests\Fakes\CancelItemFacadeBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

/**
 * @coversDefaultClass \Kiener\MolliePayments\Components\CancelManager\CancelItemFacade
 */
class CancelItemFacadeTest extends TestCase
{
    private CancelItemFacadeBuilder $cancelManagerBuilder;

    public function setUp(): void
    {
        $this->cancelManagerBuilder = new CancelItemFacadeBuilder($this);
    }

    public function testItemQuantityIsZero(): void
    {
        $cancelManager = $this->cancelManagerBuilder->bild();
        $context = Context::createDefaultContext();

        $response = $cancelManager->cancelItem('test', 'test', 'lineId', 0, false, $context);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('quantityZero', $response->getMessage());
    }

    public function testLineItemNotExistsInOrder(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withDefaultOrder();
        $cancelManager = $cancelManagerBuilder->bild();
        $context = Context::createDefaultContext();

        $response = $cancelManager->cancelItem('test', 'invalid', 'lineId', 1, false, $context);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('invalidLine', $response->getMessage());
    }

    public function testQuantityTooHigh(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withDefaultOrder();
        $cancelManager = $cancelManagerBuilder->bild();
        $context = Context::createDefaultContext();

        $response = $cancelManager->cancelItem('test', 'valid', 'lineId', 100, false, $context);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('quantityTooHigh', $response->getMessage());
    }

    public function testApiExceptionInMessage(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withInvalidOrder();
        $cancelManager = $cancelManagerBuilder->bild();
        $context = Context::createDefaultContext();

        $response = $cancelManager->cancelItem('', 'valid', 'lineId', 100, false, $context);

        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('Invalid order', $response->getMessage());
    }

    public function testCancelSuccessful(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withDefaultOrder();
        $cancelManager = $cancelManagerBuilder->bild();
        $context = Context::createDefaultContext();

        $response = $cancelManager->cancelItem('test', 'valid', 'lineId', 1, false, $context);

        $expectedData = [
            'id' => 'valid',
            'quantity' => 1,
        ];

        $this->assertTrue($response->isSuccessful());
        $this->assertSame($expectedData, $response->getData());
    }

    public function testOrderLineItemNotFound(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withDefaultOrder();

        $cancelManager = $cancelManagerBuilder->bild();
        $context = Context::createDefaultContext();

        $response = $cancelManager->cancelItem('test', 'valid', 'invalidLineId', 1, true, $context);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('invalidShopwareLineId', $response->getMessage());
    }

    public function testOrderLineStockResetSuccessful(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withDefaultOrder();
        $cancelManagerBuilder = $cancelManagerBuilder->withValidOrderLine();

        $cancelManager = $cancelManagerBuilder->bild();
        $stockManager = $cancelManagerBuilder->getStockManager();

        $context = Context::createDefaultContext();

        $response = $cancelManager->cancelItem('test', 'valid', 'lineId', 1, true, $context);

        $expectedData = [
            'id' => 'valid',
            'quantity' => 1,
        ];

        $this->assertTrue($response->isSuccessful());
        $this->assertSame($expectedData, $response->getData());
        $this->assertTrue($stockManager->isCalled());
        $this->assertSame($expectedData['quantity'], $stockManager->getQuantity());
    }
}
