<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\PaymentLink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentLink::class)]
final class PaymentLinkTest extends TestCase
{
    public function testTheLinkIdIsReadFromTheResponse(): void
    {
        $paymentLink = PaymentLink::createFromClientResponse(['id' => 'pl_1']);

        $this->assertSame('pl_1', $paymentLink->getId());
    }

    /**
     * The url the customer is sent to sits in the _links block, not at the root of the response.
     */
    public function testTheCustomerFacingUrlIsReadFromTheLinksBlock(): void
    {
        $paymentLink = PaymentLink::createFromClientResponse([
            'id' => 'pl_1',
            '_links' => ['paymentLink' => ['href' => 'https://payment.link/pl_1']],
        ]);

        $this->assertSame('https://payment.link/pl_1', $paymentLink->getUrl());
    }

    public function testAResponseWithoutALinkHasNoUrl(): void
    {
        $paymentLink = PaymentLink::createFromClientResponse(['id' => 'pl_1']);

        $this->assertSame('', $paymentLink->getUrl());
    }

    public function testAResponseWithoutAnIdHasNoId(): void
    {
        $paymentLink = PaymentLink::createFromClientResponse([]);

        $this->assertSame('', $paymentLink->getId());
    }

    public function testTheUrlCanBeSetAfterwards(): void
    {
        $paymentLink = new PaymentLink('pl_1');

        $paymentLink->setUrl('https://payment.link/pl_1');

        $this->assertSame('https://payment.link/pl_1', $paymentLink->getUrl());
    }

    public function testIdAndUrlAreWhatGetsPersistedOnTheTransaction(): void
    {
        $paymentLink = new PaymentLink('pl_1', 'https://payment.link/pl_1');

        $this->assertSame(['id' => 'pl_1', 'url' => 'https://payment.link/pl_1'], $paymentLink->toArray());
    }
}
