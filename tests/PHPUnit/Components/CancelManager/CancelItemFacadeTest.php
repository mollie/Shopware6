<?php
declare(strict_types=1);

namespace PHPUnit\Components\CancelManager;

use Kiener\MolliePayments\Components\CancelManager\CancelItemFacade;

use MolliePayments\Tests\Fakes\CancelItemFacadeBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Kiener\MolliePayments\Components\CancelManager\CancelItemFacade
 *
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

        $response = $cancelManager->cancelItem('test', 'test', 0, false);

        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('Quantity is empty', $response->getMessage());
    }

    public function testLineItemNotExistsInOrder(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withDefaultOrder();
        $cancelManager = $cancelManagerBuilder->bild();

        $response = $cancelManager->cancelItem('test', 'invalid', 1, false);

        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('not exists in order', $response->getMessage());
    }

    public function testQuantityTooHigh(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withDefaultOrder();
        $cancelManager = $cancelManagerBuilder->bild();

        $response = $cancelManager->cancelItem('test', 'valid', 100, false);

        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('Quantity too high', $response->getMessage());
    }

    public function testApiExceptionInMessage():void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withInvalidOrder();
        $cancelManager = $cancelManagerBuilder->bild();

        $response = $cancelManager->cancelItem('', 'valid', 100, false);

        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('Invalid order', $response->getMessage());
    }

    public function testCancelSuccessful(): void
    {
        $cancelManagerBuilder = $this->cancelManagerBuilder->withDefaultOrder();
        $cancelManager = $cancelManagerBuilder->bild();

        $response = $cancelManager->cancelItem('test', 'valid', 1, false);

        $expectedData = [
            'id' => 'valid',
            'quantity' => 1
        ];

        $this->assertTrue($response->isSuccessful());
        $this->assertSame($expectedData, $response->getData());
    }
}