<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Controller;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Subscription\Controller\SubscriptionController;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionActionHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeUpdateAddressRoute;
use Mollie\Shopware\Unit\Subscription\Fake\FakeWebhookRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(SubscriptionController::class)]
final class SubscriptionControllerTest extends TestCase
{
    public function testWebhookReturnsJsonResponseFromWebhookRouteOnSuccess(): void
    {
        $webhookRoute = new FakeWebhookRoute();
        $webhookRoute->setResponse(new WebhookResponse(new Payment('payment-id-1')));

        $controller = $this->getController($webhookRoute);
        $response = $controller->webhook('subscription-id-42', new Request(), new FakeSalesChannelContext());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(1, $webhookRoute->getCallCount());
        $this->assertSame('subscription-id-42', $webhookRoute->getCalls()[0]['subscriptionId']);
    }

    public function testWebhookReturnsErrorJsonWithStatusCodeOnShopwareHttpException(): void
    {
        $webhookRoute = new FakeWebhookRoute();
        $webhookRoute->setException(new class extends HttpException {
            public function __construct()
            {
                parent::__construct(Response::HTTP_BAD_REQUEST, 'TEST_CODE', 'subscription not found');
            }
        });

        $controller = $this->getController($webhookRoute);
        $response = $controller->webhook('subscription-id-42', new Request(), new FakeSalesChannelContext());

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('subscription not found', $body['error']);
    }

    public function testWebhookReturnsUnprocessableEntityOnGenericThrowable(): void
    {
        $webhookRoute = new FakeWebhookRoute();
        $webhookRoute->setException(new \RuntimeException('something exploded'));

        $controller = $this->getController($webhookRoute);
        $response = $controller->webhook('subscription-id-42', new Request(), new FakeSalesChannelContext());

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('something exploded', $body['error']);
    }

    private function getController(FakeWebhookRoute $webhookRoute): SubscriptionController
    {
        return new SubscriptionController(
            $webhookRoute,
            new FakeUpdateAddressRoute(),
            new FakeSubscriptionActionHandler(),
            new NullLogger()
        );
    }
}
