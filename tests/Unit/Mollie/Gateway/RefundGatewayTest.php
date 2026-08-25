<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreateOrderRefund;
use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundGateway::class)]
final class RefundGatewayTest extends TestCase
{
    private const SALES_CHANNEL = 'sales-channel-1';

    public function testAPaymentRefundIsPostedToThePaymentRefundEndpoint(): void
    {
        $client = new FakeClient(body: $this->refundResponse());

        $this->gateway($client)->createRefund(new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR'), 'Reason'), '10001', self::SALES_CHANNEL);

        $this->assertSame('payments/tr_1/refunds', $client->getLastUri());
    }

    public function testAPaymentRefundIsSentAsFormParametersWithAmountAndDescription(): void
    {
        $client = new FakeClient(body: $this->refundResponse());

        $this->gateway($client)->createRefund(new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR'), 'Reason'), '10001', self::SALES_CHANNEL);

        $formParams = $client->getLastPostOptions()['form_params'];

        $this->assertSame(['value' => '10.00', 'currency' => 'EUR'], $formParams['amount']);
        $this->assertSame('Reason', $formParams['description']);
    }

    public function testAnOrderRefundIsPostedToTheOrderRefundEndpoint(): void
    {
        $client = new FakeClient(body: $this->refundResponse());
        $lines = new LineItemCollection([$this->lineItem()]);

        $this->gateway($client)->createRefund(new CreateOrderRefund('ord_1', $lines), '10001', self::SALES_CHANNEL);

        $this->assertSame('orders/ord_1/refunds', $client->getLastUri());
    }

    public function testAnOrderRefundIsSentAsJsonSoTheLinesSurvive(): void
    {
        $client = new FakeClient(body: $this->refundResponse());
        $lines = new LineItemCollection([$this->lineItem()]);

        $this->gateway($client)->createRefund(new CreateOrderRefund('ord_1', $lines), '10001', self::SALES_CHANNEL);

        $options = $client->getLastPostOptions();

        $this->assertArrayNotHasKey('form_params', $options);
        $this->assertCount(1, $options['json']['lines']);
    }

    public function testAnUnknownRefundTypeIsRejectedBeforeAnyRequestIsSent(): void
    {
        $client = new FakeClient(body: $this->refundResponse());

        $unknownRefundType = new class() extends CreateRefund {
            public function toArray(): array
            {
                return [];
            }
        };

        try {
            $this->gateway($client)->createRefund($unknownRefundType, '10001', self::SALES_CHANNEL);
            self::fail('Expected a LogicException');
        } catch (\LogicException $exception) {
            $this->assertSame('', $client->getLastUri());
        }
    }

    public function testTheCreatedRefundIsBuiltFromMolliesAnswer(): void
    {
        $client = new FakeClient(body: $this->refundResponse());

        $refund = $this->gateway($client)->createRefund(new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')), '10001', self::SALES_CHANNEL);

        $this->assertSame('re_1', $refund->getId());
        $this->assertSame(RefundStatus::Refunded, $refund->getStatus());
        $this->assertSame(10.0, $refund->getAmount()->getValue());
    }

    public function testCancellingARefundDeletesItAtMollie(): void
    {
        $client = new FakeClient(body: []);

        $this->gateway($client)->cancelRefund('tr_1', 're_1', '10001', self::SALES_CHANNEL);

        $this->assertSame('DELETE', $client->getLastMethod());
        $this->assertSame('payments/tr_1/refunds/re_1', $client->getLastUri());
    }

    public function testListingRefundsReadsThemFromTheEmbeddedSection(): void
    {
        $client = new FakeClient(body: [
            '_embedded' => [
                'refunds' => [
                    $this->refundResponse('re_1', 10.0),
                    $this->refundResponse('re_2', 5.0),
                ],
            ],
        ]);

        $refunds = $this->gateway($client)->listRefunds('tr_1', '10001', self::SALES_CHANNEL);

        $this->assertCount(2, $refunds);
        $this->assertSame(15.0, $refunds->getSumRefunded());
    }

    public function testRefundsAreReadFromThePaymentsRefundEndpoint(): void
    {
        $client = new FakeClient(body: ['_embedded' => ['refunds' => []]]);

        $this->gateway($client)->listRefunds('tr_1', '10001', self::SALES_CHANNEL);

        $this->assertSame('payments/tr_1/refunds', $client->getLastUri());
    }

    public function testAPaymentWithoutRefundsYieldsAnEmptyCollection(): void
    {
        $client = new FakeClient(body: ['_embedded' => ['refunds' => []]]);

        $this->assertCount(0, $this->gateway($client)->listRefunds('tr_1', '10001', self::SALES_CHANNEL));
    }

    public function testAnAnswerWithoutAnEmbeddedSectionYieldsAnEmptyCollection(): void
    {
        $client = new FakeClient(body: []);

        $this->assertCount(0, $this->gateway($client)->listRefunds('tr_1', '10001', self::SALES_CHANNEL));
    }

    public function testAMollieErrorBecomesAnApiExceptionCarryingTitleDetailAndField(): void
    {
        $client = new FakeClient();

        try {
            $this->gateway($client)->createRefund(new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')), '10001', self::SALES_CHANNEL);
            self::fail('Expected an ApiException');
        } catch (ApiException $exception) {
            $this->assertSame('Failed Response', $exception->getTitle());
            $this->assertSame('This response failed and simulate an exception', $exception->getDetails());
            $this->assertSame('payment.id', $exception->getField());
        }
    }

    public function testAFailedListRequestAlsoBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->listRefunds('tr_1', '10001', self::SALES_CHANNEL);
    }

    public function testAFailedCancellationAlsoBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->cancelRefund('tr_1', 're_1', '10001', self::SALES_CHANNEL);
    }

    private function gateway(FakeClient $client): RefundGateway
    {
        return new RefundGateway(new FakeClientFactory($client), new FakeLogger());
    }

    private function lineItem(): LineItem
    {
        $lineItem = new LineItem('Product A', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR'));
        $lineItem->setId('odl_1');

        return $lineItem;
    }

    /**
     * @return array<string, mixed>
     */
    private function refundResponse(string $id = 're_1', float $amount = 10.0): array
    {
        return [
            'id' => $id,
            'paymentId' => 'tr_1',
            'status' => RefundStatus::Refunded->value,
            'amount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'EUR'],
            'description' => 'Refund',
            'createdAt' => '2026-08-25T10:00:00+00:00',
        ];
    }
}
