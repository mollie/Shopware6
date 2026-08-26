<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Refund;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\DAL\RefundItem\RefundItemCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Refund::class)]
final class RefundTest extends TestCase
{
    public function testCanCreateFromClientResponse(): void
    {
        $refund = Refund::createFromClientResponse($this->responseBody());

        $this->assertSame('re_test', $refund->getId());
        $this->assertSame('tr_test', $refund->getPaymentId());
        $this->assertSame(RefundStatus::Pending, $refund->getStatus());
        $this->assertEquals(new Money(10.00, 'EUR'), $refund->getAmount());
        $this->assertSame('Refund for order 10000', $refund->getDescription());
        $this->assertSame('2026-01-15T10:00:00+00:00', $refund->getCreatedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testResponseWithoutDescriptionProducesAnEmptyDescription(): void
    {
        $body = $this->responseBody();
        unset($body['description']);

        $this->assertSame('', Refund::createFromClientResponse($body)->getDescription());
    }

    public function testReturnIdIsReadFromTheMollieMetadata(): void
    {
        $body = $this->responseBody();
        $body['metadata'] = ['swagReturnId' => 'return-id'];

        $this->assertSame('return-id', Refund::createFromClientResponse($body)->getReturnId());
    }

    public function testReturnIdIsUnknownWithoutMetadata(): void
    {
        $this->assertNull(Refund::createFromClientResponse($this->responseBody())->getReturnId());
    }

    public function testNonArrayMetadataIsIgnored(): void
    {
        $body = $this->responseBody();
        $body['metadata'] = 'not-an-array';

        $this->assertNull(Refund::createFromClientResponse($body)->getReturnId());
    }

    public function testPendingRefundIsMarkedAsPendingForTheAdministration(): void
    {
        $serialized = Refund::createFromClientResponse($this->responseBody())->jsonSerialize();

        $this->assertTrue($serialized['isPending']);
        $this->assertFalse($serialized['isQueued']);
    }

    public function testQueuedRefundIsMarkedAsQueuedForTheAdministration(): void
    {
        $body = $this->responseBody();
        $body['status'] = 'queued';

        $serialized = Refund::createFromClientResponse($body)->jsonSerialize();

        $this->assertFalse($serialized['isPending']);
        $this->assertTrue($serialized['isQueued']);
    }

    public function testSerializedRefundCarriesTheMollieFields(): void
    {
        $serialized = Refund::createFromClientResponse($this->responseBody())->jsonSerialize();

        $this->assertSame('re_test', $serialized['id']);
        $this->assertSame('tr_test', $serialized['paymentId']);
        $this->assertSame('pending', $serialized['status']);
        $this->assertSame('2026-01-15T10:00:00+00:00', $serialized['createdAt']);
        $this->assertSame('', $serialized['internalDescription']);
        $this->assertSame([], $serialized['metadata']['composition']);
    }

    public function testInternalDescriptionAndCompositionAreSerialized(): void
    {
        $refundItems = new RefundItemCollection();

        $refund = Refund::createFromClientResponse($this->responseBody());
        $refund->setInternalDescription('Customer complained');
        $refund->setRefundItems($refundItems);

        $serialized = $refund->jsonSerialize();

        $this->assertSame('Customer complained', $serialized['internalDescription']);
        $this->assertSame($refundItems, $serialized['metadata']['composition']);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(): array
    {
        return [
            'id' => 're_test',
            'paymentId' => 'tr_test',
            'status' => 'pending',
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'description' => 'Refund for order 10000',
            'createdAt' => '2026-01-15T10:00:00+00:00',
        ];
    }
}
