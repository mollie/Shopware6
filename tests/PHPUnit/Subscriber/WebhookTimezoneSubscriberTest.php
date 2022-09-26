<?php declare(strict_types=1);

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Subscriber\WebhookTimezoneSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Storefront\Framework\Twig\TwigDateRequestListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class WebhookTimezoneSubscriberTest extends TestCase
{
    /**
     * @var WebhookTimezoneSubscriber
     */
    protected $subscriber;

    /**
     * @var TransactionService&MockObject
     */
    protected $transactionService;

    /**
     * @var Request
     */
    protected $request;

    public function setUp(): void
    {
        $this->transactionService = $this->createMock(TransactionService::class);

        $this->subscriber = new WebhookTimezoneSubscriber($this->transactionService, new NullLogger());
    }

    /**
     * @return void
     */
    public function testThatTimezoneIsCorrectlySet()
    {
        $expectedTimezone = 'Europe/Amsterdam';

        $this->setUpTransactionService($this->createTransactionWithOrder($expectedTimezone));
        $event = $this->setUpRequest('frontend.mollie.webhook', 'abcdef0123456789abcdef0123456789');

        $this->subscriber->fixWebhookTimezone($event);

        $this->assertEquals($expectedTimezone, $this->request->cookies->get(TwigDateRequestListener::TIMEZONE_COOKIE));
    }

    /**
     * @param string $route
     * @param string $transactionId
     * @return RequestEvent
     */
    private function setUpRequest(string $route, string $transactionId = ''): RequestEvent
    {
        $this->request = new Request();
        $this->request->attributes->set('_route', $route);

        if(!empty($transactionId)) {
            $this->request->attributes->set('_route_params', [
                'swTransactionId' => $transactionId
            ]);
        }

        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $this->request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    /**
     * Set up the transaction service with the provided transaction
     * @param OrderTransactionEntity $transaction
     * @return void
     */
    private function setUpTransactionService(OrderTransactionEntity $transaction): void
    {
        $this->transactionService->method('getTransactionById')->willReturn($transaction);
    }

    /**
     * Returns a transaction entity containing an order entity with the specified timezone set.
     * @param string $timezoneOnOrder
     * @return OrderTransactionEntity
     */
    private function createTransactionWithOrder(string $timezoneOnOrder = ''): OrderTransactionEntity
    {
        $order = $this->createMock(OrderEntity::class);
        if (!empty($timezoneOnOrder)) {
            $order->method('getCustomFields')->willReturn([
                'mollie_payments' => [
                    'timezone' => $timezoneOnOrder
                ]
            ]);
        }

        return $this->createConfiguredMock(OrderTransactionEntity::class, [
            'getOrder' => $order,
        ]);
    }
}
