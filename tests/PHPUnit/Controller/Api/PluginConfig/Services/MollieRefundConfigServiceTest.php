<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Controller\Api\PluginConfig\Services;

use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions\EmptyOrderIdProvidedConfigException;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions\MollieRefundConfigException;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Services\MollieRefundConfigService;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Structs\Collections\OrderLineItemStructCollection;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Structs\OrderLineItemStruct;
use Kiener\MolliePayments\Service\MollieApi\Order as MollieOrderService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class MollieRefundConfigServiceTest extends TestCase
{
    /**
     * @var MollieSettingStruct
     */
    private $config;

    /**
     * @var MollieRefundConfigService
     */
    private $service;

    protected function setUp(): void
    {
        $this->config = new MollieSettingStruct();
        $this->config->setRefundManagerEnabled(true);
        $this->config->setRefundManagerAutoStockReset(true);
        $this->config->setRefundManagerVerifyRefund(true);
        $this->config->setRefundManagerShowInstructions(true);

        $this->service = new MollieRefundConfigService(
            $this->createMock(OrderService::class),
            $this->createMock(RefundManagerInterface::class),
            $this->createMock(MollieOrderService::class)
        );
    }

    /**
     * @throws MollieRefundConfigException
     */
    public function testCreateConfigControllerResponseThrowsExceptionIfOrderIdIsEmpty(): void
    {
        $this->expectException(EmptyOrderIdProvidedConfigException::class);
        $this->service->createConfigControllerResponse('', $this->createMock(MollieSettingStruct::class), 'salesChannelId', $this->createMock(Context::class));
    }

    public function testEnablesRefundManagerWhenPendingRefundExists(): void
    {
        $lineItems = OrderLineItemStructCollection::create(
            $lineItem = $this->createMock(OrderLineItemStruct::class)
        );

        $lineItem->expects(static::once())
            ->method('hasPendingRefund')
            ->willReturn(true)
        ;

        $lineItem->expects($this->never())->method('getRefundableQuantity');
        $lineItem->expects($this->never())->method('getRefundedCount');
        $lineItem->expects($this->never())->method('getOrderedQuantity');

        $result = $this->service->createResponse($lineItems, $this->config);

        static::assertSame(
            '{"enabled":true,"autoStockReset":true,"verifyRefund":true,"showInstructions":true}',
            $result->getContent()
        );
    }

    public function testRefundManagerIsEnabledWhenRefundableQuantityForLineItemIsGreaterThanZero(): void
    {
        $lineItems = OrderLineItemStructCollection::create(
            $lineItem = $this->createMock(OrderLineItemStruct::class)
        );

        $lineItem->expects(static::once())
            ->method('hasPendingRefund')
            ->willReturn(false)
        ;

        $lineItem->expects($this->once())
            ->method('getRefundableQuantity')
            ->willReturn(1)
        ;

        $lineItem->expects($this->once())
            ->method('getRefundedCount')
            ->willReturn(6)
        ;

        $lineItem->expects($this->once())
            ->method('getOrderedQuantity')
            ->willReturn(5)
        ;

        $result = $this->service->createResponse($lineItems, $this->config);

        static::assertSame(
            '{"enabled":true,"autoStockReset":true,"verifyRefund":true,"showInstructions":true}',
            $result->getContent()
        );
    }

    public function testRefundManagerIsDisabledWhenAllLineItemsHaveBeenRefunded(): void
    {
        $lineItems = OrderLineItemStructCollection::create(
            $lineItem = $this->createMock(OrderLineItemStruct::class)
        );

        $lineItem->expects(static::once())
            ->method('hasPendingRefund')
            ->willReturn(false)
        ;

        $lineItem->expects($this->once())
            ->method('getRefundableQuantity')
            ->willReturn(0)
        ;

        $lineItem->expects($this->once())
            ->method('getRefundedCount')
            ->willReturn(5)
        ;

        $lineItem->expects($this->once())
            ->method('getOrderedQuantity')
            ->willReturn(5)
        ;

        $result = $this->service->createResponse($lineItems, $this->config);

        static::assertSame(
            '{"enabled":false,"autoStockReset":true,"verifyRefund":true,"showInstructions":true}',
            $result->getContent()
        );
    }
}
