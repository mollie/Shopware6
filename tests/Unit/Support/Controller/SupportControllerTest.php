<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Controller;

use Mollie\Shopware\Component\Support\Controller\SupportController;
use Mollie\Shopware\Unit\Fake\FakeSupportMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(SupportController::class)]
final class SupportControllerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testRequestSupportReturnsSuccess(): void
    {
        $mailer = new FakeSupportMailer();
        $controller = new SupportController($mailer, new NullLogger());

        $request = new Request([], [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'recipientLocale' => 'de-DE',
            'subject' => 'Test issue',
            'message' => 'Something went wrong.',
        ]);

        $response = $controller->requestSupport($request, $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertTrue($body['success']);
        $this->assertTrue($mailer->wasCalledOnce());
    }

    public function testRequestSupportPassesAllParamsToMailer(): void
    {
        $mailer = new FakeSupportMailer();
        $controller = new SupportController($mailer, new NullLogger());

        $request = new Request([], [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'recipientLocale' => 'en-GB',
            'subject' => 'Payment issue',
            'message' => 'Payment not working.',
        ]);

        $controller->requestSupport($request, $this->context);

        $call = $mailer->getLastCall();
        $this->assertNotNull($call);
        $this->assertSame('Jane Doe', $call['name']);
        $this->assertSame('jane@example.com', $call['email']);
        $this->assertSame('en-GB', $call['recipientLocale']);
        $this->assertSame('Payment issue', $call['subject']);
        $this->assertSame('Payment not working.', $call['message']);
    }

    public function testRequestSupportReturnsErrorOnException(): void
    {
        $mailer = new FakeSupportMailer();
        $mailer->throwOnSend(new \RuntimeException('Mail server down'));

        $controller = new SupportController($mailer, new NullLogger());

        $request = new Request([], [
            'name' => 'John',
            'email' => 'john@example.com',
            'recipientLocale' => 'de-DE',
            'subject' => 'Issue',
            'message' => 'Help!',
        ]);

        $response = $controller->requestSupport($request, $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertSame('Mail server down', $body['error']);
    }
}
