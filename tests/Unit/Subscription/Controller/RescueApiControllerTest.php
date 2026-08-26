<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Controller;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Subscription\Controller\RescueApiController;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionStatus;
use Mollie\Shopware\Unit\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The rescue controller is the admin's way out when a subscription only exists at Mollie: it reads
 * the customer's Mollie subscriptions directly from the API and can cancel one by its Mollie id.
 */
#[CoversClass(RescueApiController::class)]
final class RescueApiControllerTest extends TestCase
{
    private const CUSTOMER_ID = 'cust-1';
    private const SALES_CHANNEL_ID = 'sc-1';

    private Context $context;

    private FakeCustomerRepository $customerRepository;

    private FakeSubscriptionGateway $subscriptionGateway;

    private FakeGateway $mollieGateway;

    private FakeSubscriptionRepository $subscriptionRepository;

    private FakeSettingsService $settingsService;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->customerRepository = new FakeCustomerRepository();
        $this->subscriptionGateway = new FakeSubscriptionGateway();
        $this->mollieGateway = new FakeGateway();
        $this->subscriptionRepository = new FakeSubscriptionRepository();
        $this->settingsService = new FakeSettingsService();
    }

    // -------------------------------------------------------------------- list

    public function testListReturnsErrorWhenCustomerIsMissing(): void
    {
        $response = $this->controller()->listUserMollieSubscriptions('missing-customer', $this->context);

        $this->assertSame(500, $response->getStatusCode());
        $body = $this->decode($response->getContent());
        $this->assertFalse($body['success']);
        $this->assertSame(['Customer with ID missing-customer not found in Shopware'], $body['errors']);
    }

    public function testListReturnsEmptySubscriptionsWhenCustomerHasNoMollieIds(): void
    {
        $this->givenCustomer();

        $response = $this->controller()->listUserMollieSubscriptions(self::CUSTOMER_ID, $this->context);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decode($response->getContent());
        $this->assertTrue($body['success']);
        $this->assertSame([], $body['subscriptions']);
    }

    /**
     * The customer id is looked up lowercased, because that is how Shopware stores it.
     */
    public function testListLooksTheCustomerUpLowercased(): void
    {
        $this->givenCustomer();

        $response = $this->controller()->listUserMollieSubscriptions('CUST-1', $this->context);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testListMapsTheMollieSubscriptionsOfTheCustomer(): void
    {
        $this->givenCustomer(['test' => 'cst_test1']);
        $this->subscriptionGateway->register(
            MollieSubscriptionBuilder::create()
                ->withId('sub_1')
                ->withCustomerId('cst_test1')
                ->build()
        );

        $response = $this->controller()->listUserMollieSubscriptions(self::CUSTOMER_ID, $this->context);

        $body = $this->decode($response->getContent());
        $this->assertCount(1, $body['subscriptions']);
        $this->assertSame('sub_1', $body['subscriptions'][0]['id']);
        $this->assertSame('cst_test1', $body['subscriptions'][0]['customerId']);
    }

    /**
     * The custom fields hold one Mollie customer per profile and mode. In test mode only the test
     * ids may be asked for - a live id would be unknown to the test API and error out.
     */
    public function testListReadsTheMollieCustomerIdOfTheActiveMode(): void
    {
        $this->givenCustomer(['test' => 'cst_test1', 'live' => 'cst_live1']);
        $this->subscriptionGateway->register(
            MollieSubscriptionBuilder::create()->withId('sub_1')->withCustomerId('cst_test1')->build()
        );

        $this->controller()->listUserMollieSubscriptions(self::CUSTOMER_ID, $this->context);

        $calls = $this->subscriptionGateway->getCalls('listSubscriptionsForCustomer');
        $this->assertSame('cst_test1', $calls[0]['customerId']);
    }

    public function testListReadsTheLiveMollieCustomerIdInLiveMode(): void
    {
        $this->givenCustomer(['test' => 'cst_test1', 'live' => 'cst_live1']);
        $this->settingsService = new FakeSettingsService(
            apiSettings: new ApiSettings('test_key', 'live_key', Mode::LIVE, '')
        );
        $this->subscriptionGateway->register(
            MollieSubscriptionBuilder::create()->withId('sub_1')->withCustomerId('cst_live1')->build()
        );

        $this->controller()->listUserMollieSubscriptions(self::CUSTOMER_ID, $this->context);

        $calls = $this->subscriptionGateway->getCalls('listSubscriptionsForCustomer');
        $this->assertSame('cst_live1', $calls[0]['customerId']);
    }

    public function testListAsksMollieOncePerDistinctCustomerId(): void
    {
        $this->givenCustomerWithProfiles([
            'profile-1' => ['test' => 'cst_test1'],
            'profile-2' => ['test' => 'cst_test1'],
            'profile-3' => ['test' => 'cst_test2'],
        ]);
        $this->subscriptionGateway->register(
            MollieSubscriptionBuilder::create()->withId('sub_1')->withCustomerId('cst_test1')->build()
        );

        $this->controller()->listUserMollieSubscriptions(self::CUSTOMER_ID, $this->context);

        $this->assertSame(2, $this->subscriptionGateway->getCallCount('listSubscriptionsForCustomer'));
    }

    public function testListReturnsAnErrorWhenMollieCannotBeReached(): void
    {
        $this->givenCustomer(['test' => 'cst_test1']);
        $this->subscriptionGateway->throwOnListForCustomer(new \RuntimeException('Mollie API unavailable'));

        $response = $this->controller()->listUserMollieSubscriptions(self::CUSTOMER_ID, $this->context);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['Mollie API unavailable'], $this->decode($response->getContent())['errors']);
    }

    // ------------------------------------------------------------------ cancel

    public function testCancelReportsTheCancelledSubscription(): void
    {
        $this->subscriptionGateway->register(
            MollieSubscriptionBuilder::create()->withId('sub_1')->withCustomerId('cst_test1')->build()
        );

        $response = $this->cancel();

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decode($response->getContent());
        $this->assertTrue($body['success']);
        $this->assertSame('sub_1', $body['subscription']['id']);
    }

    public function testCancelPassesTheSubscriptionAndCustomerToMollie(): void
    {
        $this->subscriptionGateway->register(
            MollieSubscriptionBuilder::create()->withId('sub_1')->withCustomerId('cst_test1')->build()
        );

        $this->cancel();

        $calls = $this->subscriptionGateway->getCalls('cancelSubscription');
        $this->assertSame('sub_1', $calls[0]['subscriptionId']);
        $this->assertSame('cst_test1', $calls[0]['customerId']);
    }

    /**
     * The whole point of the rescue route: when the cancel itself fails, the mandate is revoked so
     * Mollie can no longer charge the customer with it.
     */
    public function testCancelRevokesTheMandateWhenMollieRefusesTheCancel(): void
    {
        $response = $this->cancel();

        $this->assertSame(
            [['mollieCustomerId' => 'cst_test1', 'mandateId' => 'mdt_1']],
            $this->mollieGateway->getRevokedMandates()
        );
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * With the mandate revoked the customer is safe from further charges, so the admin UI is told
     * the subscription is cancelled even though Mollie never confirmed it.
     */
    public function testCancelReportsTheSubscriptionAsCancelledAfterRevokingTheMandate(): void
    {
        $body = $this->decode($this->cancel()->getContent());

        $this->assertSame('sub_1', $body['subscription']['id']);
        $this->assertSame(SubscriptionStatus::CANCELED, $body['subscription']['status']);
    }

    public function testCancelMarksTheLocalSubscriptionAsCancelled(): void
    {
        $this->subscriptionRepository->add($this->localSubscription());

        $this->cancel();

        $upsert = $this->subscriptionRepository->getLastUpsert();
        $this->assertSame('local-subscription-1', $upsert['id']);
        $this->assertSame(SubscriptionStatus::CANCELED, $upsert['status']);
        $this->assertNull($upsert['nextPaymentAt']);
    }

    /**
     * The history entry is what the merchant sees in the admin, so it has to record where the
     * subscription came from and that it was cancelled.
     */
    public function testCancelWritesAHistoryEntryForTheLocalSubscription(): void
    {
        $this->subscriptionRepository->add($this->localSubscription());

        $this->cancel();

        $history = $this->subscriptionRepository->getLastUpsert()['historyEntries'][0];
        $this->assertSame('active', $history['statusFrom']);
        $this->assertSame(SubscriptionStatus::CANCELED, $history['statusTo']);
        $this->assertSame('sub_1', $history['mollieId']);
    }

    public function testCancelWritesNothingWhenTheSubscriptionIsUnknownLocally(): void
    {
        $this->cancel();

        $this->assertSame(0, $this->subscriptionRepository->getUpsertCount());
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param array<string, string> $mollieCustomerIds keyed by mode, as one profile stores them
     */
    private function givenCustomer(array $mollieCustomerIds = []): void
    {
        $this->givenCustomerWithProfiles($mollieCustomerIds === [] ? [] : ['profile-1' => $mollieCustomerIds]);
    }

    /**
     * @param array<string, array<string, string>> $profiles Mollie customer ids per profile and mode
     */
    private function givenCustomerWithProfiles(array $profiles): void
    {
        $customer = new CustomerEntity();
        $customer->setId(self::CUSTOMER_ID);
        $customer->setSalesChannelId(self::SALES_CHANNEL_ID);
        $customer->setCustomFields(
            $profiles === [] ? [] : ['mollie_payments' => ['customer_ids' => $profiles]]
        );

        $this->customerRepository->add($customer);
    }

    private function localSubscription(): SubscriptionEntity
    {
        $subscription = new SubscriptionEntity();
        $subscription->setId('local-subscription-1');
        $subscription->setMollieId('sub_1');
        $subscription->setStatus('active');

        return $subscription;
    }

    private function cancel(): JsonResponse
    {
        return $this->controller()->cancelByMollieId(
            'cst_test1',
            'sub_1',
            'mdt_1',
            self::SALES_CHANNEL_ID,
            $this->context
        );
    }

    private function controller(): RescueApiController
    {
        return new RescueApiController(
            $this->subscriptionGateway,
            $this->mollieGateway,
            $this->customerRepository,
            $this->subscriptionRepository,
            $this->settingsService,
            new NullLogger()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string|false $content): array
    {
        return json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
