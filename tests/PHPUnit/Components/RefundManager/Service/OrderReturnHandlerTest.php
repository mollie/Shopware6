<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\RefundManager\Service;

use Kiener\MolliePayments\Components\RefundManager\RefundData\RefundData;
use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Service\OrderReturnHandler;
use Mollie\Api\Resources\Refund;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

#[\PHPUnit\Framework\Attributes\CoversClass(OrderReturnHandler::class)]
class OrderReturnHandlerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    /**
     * This test verifies that return() does nothing and does not call the refund manager
     * when the order_return repository is unavailable (SwagCommercial not installed).
     */
    public function testReturnDoesNothingWhenFeatureDisabled(): void
    {
        $tracker = (object) ['refundCalled' => false];

        $fakeRefundManager = new class($tracker) implements RefundManagerInterface {
            private object $tracker;

            public function __construct(object $tracker)
            {
                $this->tracker = $tracker;
            }

            public function getData(OrderEntity $order, Context $context): RefundData
            {
                return new RefundData([], []);
            }

            public function refund(OrderEntity $order, RefundRequest $request, Context $context): Refund
            {
                $this->tracker->refundCalled = true;

                return new Refund(null);
            }

            public function cancelAllOrderRefunds(OrderEntity $order, Context $context): bool
            {
                return false;
            }

            public function cancelRefund(string $orderId, string $refundId, Context $context): bool
            {
                return false;
            }
        };

        $handler = new OrderReturnHandler($fakeRefundManager, null, new NullLogger());
        $handler->return('return-abc', $this->context);

        self::assertFalse($tracker->refundCalled, 'refund() must not be called when feature is disabled');
    }

    /**
     * This test verifies that cancel() does nothing and does not call the refund manager
     * when the order_return repository is unavailable (SwagCommercial not installed).
     */
    public function testCancelDoesNothingWhenFeatureDisabled(): void
    {
        $tracker = (object) ['cancelCalled' => false];

        $fakeRefundManager = new class($tracker) implements RefundManagerInterface {
            private object $tracker;

            public function __construct(object $tracker)
            {
                $this->tracker = $tracker;
            }

            public function getData(OrderEntity $order, Context $context): RefundData
            {
                return new RefundData([], []);
            }

            public function refund(OrderEntity $order, RefundRequest $request, Context $context): Refund
            {
                return new Refund(null);
            }

            public function cancelAllOrderRefunds(OrderEntity $order, Context $context): bool
            {
                $this->tracker->cancelCalled = true;

                return false;
            }

            public function cancelRefund(string $orderId, string $refundId, Context $context): bool
            {
                return false;
            }
        };

        $handler = new OrderReturnHandler($fakeRefundManager, null, new NullLogger());
        $handler->cancel('return-abc', $this->context);

        self::assertFalse($tracker->cancelCalled, 'cancelAllOrderRefunds() must not be called when feature is disabled');
    }

    /**
     * This test verifies that returnOnCreatedAsDone() does nothing
     * when the feature is disabled (SwagCommercial not installed).
     */
    public function testReturnOnCreatedAsDoneDoesNothingWhenFeatureDisabled(): void
    {
        $tracker = (object) ['refundCalled' => false];

        $fakeRefundManager = new class($tracker) implements RefundManagerInterface {
            private object $tracker;

            public function __construct(object $tracker)
            {
                $this->tracker = $tracker;
            }

            public function getData(OrderEntity $order, Context $context): RefundData
            {
                return new RefundData([], []);
            }

            public function refund(OrderEntity $order, RefundRequest $request, Context $context): Refund
            {
                $this->tracker->refundCalled = true;

                return new Refund(null);
            }

            public function cancelAllOrderRefunds(OrderEntity $order, Context $context): bool
            {
                return false;
            }

            public function cancelRefund(string $orderId, string $refundId, Context $context): bool
            {
                return false;
            }
        };

        $handler = new OrderReturnHandler($fakeRefundManager, null, new NullLogger());
        $handler->returnOnCreatedAsDone('return-abc', $this->context);

        self::assertFalse($tracker->refundCalled, 'refund() must not be called when feature is disabled');
    }
}
