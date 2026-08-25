<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionGateway::class)]
final class SubscriptionGatewayTest extends TestCase
{
    private const CUSTOMER = 'cst_1';
    private const ORDER_NUMBER = '10001';
    private const SALES_CHANNEL = 'sales-channel-1';

    public function testANewSubscriptionIsPostedToTheCustomersSubscriptionEndpoint(): void
    {
        $client = new FakeClient(body: $this->subscriptionResponse());

        $this->gateway($client)->createSubscription($this->createSubscription(), self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame('customers/cst_1/subscriptions', $client->getLastUri());
    }

    public function testTheCreatedSubscriptionIsBuiltFromMolliesAnswer(): void
    {
        $client = new FakeClient(body: $this->subscriptionResponse());

        $subscription = $this->gateway($client)->createSubscription($this->createSubscription(), self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame('sub_1', $subscription->getId());
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
        $this->assertSame(19.99, $subscription->getAmount()->getValue());
        $this->assertSame(1, $subscription->getInterval()->getIntervalValue());
        $this->assertSame(IntervalUnit::MONTHS, $subscription->getInterval()->getIntervalUnit());
    }

    public function testASubscriptionIsReadFromTheCustomersSubscriptionEndpoint(): void
    {
        $client = new FakeClient(body: $this->subscriptionResponse());

        $this->gateway($client)->getSubscription('sub_1', self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame('customers/cst_1/subscriptions/sub_1', $client->getLastUri());
    }

    public function testCancellingASubscriptionDeletesItAtMollie(): void
    {
        $client = new FakeClient(body: $this->subscriptionResponse(status: SubscriptionStatus::CANCELED));

        $subscription = $this->gateway($client)->cancelSubscription('sub_1', self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame('DELETE', $client->getLastMethod());
        $this->assertSame('customers/cst_1/subscriptions/sub_1', $client->getLastUri());
        $this->assertSame(SubscriptionStatus::CANCELED, $subscription->getStatus());
    }

    public function testAnUpdateIsPatchedSoUntouchedFieldsStayAsTheyAre(): void
    {
        $client = new FakeClient(body: $this->subscriptionResponse());

        $this->gateway($client)->updateSubscription($this->subscription(), self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame('customers/cst_1/subscriptions/sub_1', $client->getLastUri());
        $this->assertArrayHasKey('form_params', $client->getLastPatchOptions());
    }

    public function testACopiedSubscriptionKeepsDescriptionIntervalAndAmountOfTheOriginal(): void
    {
        $client = new FakeClient(body: $this->subscriptionResponse());

        $this->gateway($client)->copySubscription($this->subscription(), self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $formParams = $client->getLastPostOptions()['form_params'];

        $this->assertSame('Monthly box', $formParams['description']);
        $this->assertSame('1 month', $formParams['interval']);
        $this->assertSame(['value' => '19.99', 'currency' => 'EUR'], $formParams['amount']);
    }

    public function testACopiedSubscriptionKeepsTheMandateSoTheCustomerIsNotAskedToPayAgain(): void
    {
        $client = new FakeClient(body: $this->subscriptionResponse());

        $this->gateway($client)->copySubscription($this->subscription(), self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame('mdt_1', $client->getLastPostOptions()['form_params']['mandateId']);
    }

    public function testACopiedSubscriptionIsCreatedAtTheCustomersEndpoint(): void
    {
        $client = new FakeClient(body: $this->subscriptionResponse());

        $this->gateway($client)->copySubscription($this->subscription(), self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame('customers/cst_1/subscriptions', $client->getLastUri());
    }

    public function testListedSubscriptionsAreKeyedByTheirMollieId(): void
    {
        $client = new FakeClient(body: [
            '_embedded' => [
                'subscriptions' => [
                    $this->subscriptionResponse('sub_1'),
                    $this->subscriptionResponse('sub_2'),
                ],
            ],
        ]);

        $subscriptions = $this->gateway($client)->listSubscriptions(null, 50, self::SALES_CHANNEL);

        $this->assertCount(2, $subscriptions);
        $this->assertSame('sub_2', $subscriptions->get('sub_2')?->getId());
    }

    public function testTheListRequestCarriesTheRequestedPageSize(): void
    {
        $client = new FakeClient(body: ['_embedded' => ['subscriptions' => []]]);

        $this->gateway($client)->listSubscriptions(null, 50, self::SALES_CHANNEL);

        $this->assertSame(['limit' => 50], $client->getLastGetOptions()['query']);
    }

    public function testTheListRequestContinuesFromTheGivenSubscription(): void
    {
        $client = new FakeClient(body: ['_embedded' => ['subscriptions' => []]]);

        $this->gateway($client)->listSubscriptions('sub_5', 50, self::SALES_CHANNEL);

        $this->assertSame(['limit' => 50, 'from' => 'sub_5'], $client->getLastGetOptions()['query']);
    }

    public function testAnEmptyCursorIsNotSentAsAStartingPoint(): void
    {
        $client = new FakeClient(body: ['_embedded' => ['subscriptions' => []]]);

        $this->gateway($client)->listSubscriptions('', 50, self::SALES_CHANNEL);

        $this->assertArrayNotHasKey('from', $client->getLastGetOptions()['query']);
    }

    public function testAnAccountWithoutSubscriptionsYieldsAnEmptyCollection(): void
    {
        $client = new FakeClient(body: []);

        $this->assertCount(0, $this->gateway($client)->listSubscriptions(null, 50, self::SALES_CHANNEL));
    }

    public function testCustomerSubscriptionsAreReadFromTheCustomersEndpoint(): void
    {
        $client = new FakeClient(body: ['_embedded' => ['subscriptions' => [$this->subscriptionResponse()]]]);

        $subscriptions = $this->gateway($client)->listSubscriptionsForCustomer(self::CUSTOMER, self::SALES_CHANNEL);

        $this->assertSame('customers/cst_1/subscriptions', $client->getLastUri());
        $this->assertCount(1, $subscriptions);
    }

    public function testAMollieErrorBecomesAnApiExceptionCarryingTheOrderNumber(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->getSubscription('sub_1', self::CUSTOMER, self::ORDER_NUMBER, self::SALES_CHANNEL);
    }

    public function testAFailedListRequestAlsoBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->listSubscriptions(null, 50, self::SALES_CHANNEL);
    }

    private function gateway(FakeClient $client): SubscriptionGateway
    {
        return new SubscriptionGateway(new FakeClientFactory($client), new FakeLogger());
    }

    private function createSubscription(): CreateSubscription
    {
        return new CreateSubscription('Monthly box', new Interval(1, IntervalUnit::MONTHS), new Money(19.99, 'EUR'));
    }

    private function subscription(): Subscription
    {
        return new Subscription(
            'sub_1',
            self::CUSTOMER,
            'mdt_1',
            SubscriptionStatus::ACTIVE,
            new Interval(1, IntervalUnit::MONTHS),
            new Money(19.99, 'EUR'),
            'Monthly box',
            'https://shop.test/webhook',
            [],
            new \DateTime('2026-09-01')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionResponse(string $id = 'sub_1', SubscriptionStatus $status = SubscriptionStatus::ACTIVE): array
    {
        return [
            'id' => $id,
            'customerId' => self::CUSTOMER,
            'mandateId' => 'mdt_1',
            'description' => 'Monthly box',
            'webhookUrl' => 'https://shop.test/webhook',
            'amount' => ['value' => '19.99', 'currency' => 'EUR'],
            'startDate' => '2026-09-01',
            'createdAt' => '2026-08-25T10:00:00+00:00',
            'interval' => '1 month',
            'status' => $status->value,
        ];
    }
}
