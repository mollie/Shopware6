<?php declare(strict_types=1);

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Subscriber\WebhookTimezoneSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Storefront\Framework\Twig\TwigDateRequestListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class WebhookTimezoneSubscriberTest extends TestCase
{
    private const WEBHOOK_ROUTE = 'frontend.mollie.webhook';
    private const TRANSACTION_ID = 'abcdef0123456789abcdef0123456789';
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
     * Tests that the timezone is correctly set on the request when:
     * 1: We're in the Mollie Webhook route
     * 2: We have a swTransactionId, and it's valid
     * 3: We can get an OrderTransactionEntity using the swTransactionId
     * 4: The transaction entity contains an OrderEntity
     * 5: And the order entity has a timezone set on its customFields.
     *
     * @return void
     */
    public function testThatTimezoneIsCorrectlySet()
    {
        $expectedTimezone = 'Europe/Amsterdam';

        $this->setUpTransactionService($this->createTransactionWithOrder($expectedTimezone));
        $event = $this->setUpRequest(self::WEBHOOK_ROUTE, self::TRANSACTION_ID);

        $this->transactionService->expects($this->once())->method('getTransactionById');

        $this->subscriber->fixWebhookTimezone($event);

        $this->assertArrayHasKey(TwigDateRequestListener::TIMEZONE_COOKIE, $this->request->cookies->all());
        $this->assertEquals($expectedTimezone, $this->request->cookies->get(TwigDateRequestListener::TIMEZONE_COOKIE));
    }

    /**
     * Tests that the timezone cookie is not set by us when we're not on the mollie webhook route.
     * @return void
     */
    public function testThatTimezoneIsNotSetWithIncorrectRoute()
    {
        $orderTimezone = 'Europe/Amsterdam';

        $this->setUpTransactionService($this->createTransactionWithOrder($orderTimezone));
        $event = $this->setUpRequest('some.random.route', self::TRANSACTION_ID);

        $this->transactionService->expects($this->never())->method('getTransactionById');

        $this->subscriber->fixWebhookTimezone($event);

        $this->assertArrayNotHasKey(TwigDateRequestListener::TIMEZONE_COOKIE, $this->request->cookies->all());
        $this->assertNull($this->request->cookies->get(TwigDateRequestListener::TIMEZONE_COOKIE));
    }

    /**
     * Test that the timezone cookie is not set if we don't have a transaction id
     * @return void
     */
    public function testThatTimezoneIsNotSetWithNoTransactionId()
    {
        $orderTimezone = 'Europe/Amsterdam';

        $this->setUpTransactionService($this->createTransactionWithOrder($orderTimezone));
        $event = $this->setUpRequest(self::WEBHOOK_ROUTE);

        $this->transactionService->expects($this->never())->method('getTransactionById');

        $this->subscriber->fixWebhookTimezone($event);

        $this->assertArrayNotHasKey(TwigDateRequestListener::TIMEZONE_COOKIE, $this->request->cookies->all());
        $this->assertNull($this->request->cookies->get(TwigDateRequestListener::TIMEZONE_COOKIE));
    }

    /**
     * Tests that the timezone cookie is not set if we have an invalid transaction id
     * @return void
     */
    public function testThatTimezoneIsNotSetWithIncorrectTransactionId()
    {
        $orderTimezone = 'Europe/Amsterdam';

        $this->setUpTransactionService($this->createTransactionWithOrder($orderTimezone));
        $event = $this->setUpRequest(self::WEBHOOK_ROUTE, 'foo');

        $this->transactionService->expects($this->never())->method('getTransactionById');

        $this->subscriber->fixWebhookTimezone($event);

        $this->assertArrayNotHasKey(TwigDateRequestListener::TIMEZONE_COOKIE, $this->request->cookies->all());
        $this->assertNull($this->request->cookies->get(TwigDateRequestListener::TIMEZONE_COOKIE));
    }

    /**
     * Tests that the timezone cookie is not set if we don't have a transaction entity
     * @return void
     */
    public function testThatTimezoneIsNotSetWithoutATransaction()
    {
        $this->setUpTransactionService(null);
        $event = $this->setUpRequest(self::WEBHOOK_ROUTE, self::TRANSACTION_ID);

        $this->transactionService->expects($this->once())->method('getTransactionById');

        $this->subscriber->fixWebhookTimezone($event);

        $this->assertArrayNotHasKey(TwigDateRequestListener::TIMEZONE_COOKIE, $this->request->cookies->all());
        $this->assertNull($this->request->cookies->get(TwigDateRequestListener::TIMEZONE_COOKIE));
    }

    /**
     * Tests that the timezone cookie is not set if we don't have an order entity
     * @return void
     */
    public function testThatTimezoneIsNotSetWithoutAnOrder()
    {
        $this->setUpTransactionService($this->createMock(OrderTransactionEntity::class));
        $event = $this->setUpRequest(self::WEBHOOK_ROUTE, self::TRANSACTION_ID);

        $this->transactionService->expects($this->once())->method('getTransactionById');

        $this->subscriber->fixWebhookTimezone($event);

        $this->assertArrayNotHasKey(TwigDateRequestListener::TIMEZONE_COOKIE, $this->request->cookies->all());
        $this->assertNull($this->request->cookies->get(TwigDateRequestListener::TIMEZONE_COOKIE));
    }

    /**
     * Tests that the timezone cookie is not set if there's no timezone set on the order
     * @return void
     */
    public function testThatTimezoneIsNotSetWithoutATimezoneCustomField()
    {
        $this->setUpTransactionService($this->createTransactionWithOrder());
        $event = $this->setUpRequest(self::WEBHOOK_ROUTE, self::TRANSACTION_ID);

        $this->transactionService->expects($this->once())->method('getTransactionById');

        $this->subscriber->fixWebhookTimezone($event);

        $this->assertArrayNotHasKey(TwigDateRequestListener::TIMEZONE_COOKIE, $this->request->cookies->all());
        $this->assertNull($this->request->cookies->get(TwigDateRequestListener::TIMEZONE_COOKIE));
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
     * @param $transaction
     * @return void
     */
    private function setUpTransactionService($transaction): void
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
