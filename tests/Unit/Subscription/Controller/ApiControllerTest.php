<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Controller;

use Mollie\Shopware\Component\Subscription\Controller\ApiController;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionActionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ApiController::class)]
final class ApiControllerTest extends TestCase
{
    public function testChangeStateReturnsSubscriptionDataOnSuccess(): void
    {
        $mollieSubscription = MollieSubscriptionBuilder::create()->withId('sub_test123')->build();

        $handler = new FakeSubscriptionActionHandler();
        $handler->setResponse($mollieSubscription);

        $controller = new ApiController($handler);

        $request = $this->buildRequest('subscription-id-42', 'pause');
        $response = $controller->changeState($request, Context::createDefaultContext());

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('subscription', $body);
        $this->assertSame(1, $handler->getCallCount());
        $this->assertSame('pause', $handler->getCalls()[0]['action']);
        $this->assertSame('subscription-id-42', $handler->getCalls()[0]['subscriptionId']);
    }

    public function testChangeStateReturnsErrorMessageOnException(): void
    {
        $handler = new FakeSubscriptionActionHandler();
        $handler->setException(new \RuntimeException('something broke'));

        $controller = new ApiController($handler);

        $request = $this->buildRequest('subscription-id-42', 'cancel');
        $response = $controller->changeState($request, Context::createDefaultContext());

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('something broke', $body['error']);
        $this->assertArrayNotHasKey('subscription', $body);
    }

    public function testChangeStateReadsSubscriptionIdFromRequestBody(): void
    {
        $handler = new FakeSubscriptionActionHandler();
        $handler->setResponse(MollieSubscriptionBuilder::create()->build());

        $controller = new ApiController($handler);

        $request = $this->buildRequest('explicit-id', 'skip');
        $controller->changeState($request, Context::createDefaultContext());

        $this->assertSame('explicit-id', $handler->getCalls()[0]['subscriptionId']);
        $this->assertSame('skip', $handler->getCalls()[0]['action']);
    }

    private function buildRequest(string $subscriptionId, string $action): Request
    {
        $request = new Request(request: ['id' => $subscriptionId]);
        $request->attributes->set('action', $action);

        return $request;
    }
}
