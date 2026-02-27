<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\Subscription\Action;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\Actions\ResumeAction;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Gateway\Mollie\MollieGateway;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Resources\Subscription;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Subscription\DAL\Repository\SubscriptionRepository;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

class ResumeActionTest extends TestCase
{
    /**
     * This test ensures that when a subscription is cancelled before the next payment date, then a resume will restart the subscription with the next payment date
     *
     * @throws \Exception
     */
    public function testResumeDateIsBeforeNextPaymentDate(): void
    {
        $mollieGateWay = $this->createMock(MollieGateway::class);
        $mockGateWaySubscription = $this->createMock(Subscription::class);
        $mockGateWaySubscription->startDate = '2025-08-13';
        $mockGateWaySubscription->interval = '2 months';
        $mollieGateWayJsonPayload = [];
        $mollieGateWay->method('getSubscription')->willReturn($mockGateWaySubscription);
        $mollieGateWay->method('createSubscription')->willReturnCallback(function (string $subscriptionId, array $jsonPayload) use ($mockGateWaySubscription, &$mollieGateWayJsonPayload) {
            $mollieGateWayJsonPayload = $jsonPayload;

            return $mockGateWaySubscription;
        });

        $resumeAction = $this->getAction($mollieGateWay);

        $context = Context::createDefaultContext();
        $today = \DateTime::createFromFormat('Y-m-d', '2025-10-08');
        $resumeAction->resumeSubscription('fake', $today, $context);
        $expectedStartDate = '2025-10-13';
        $actualStartDate = $mollieGateWayJsonPayload['startDate'];
        $this->assertSame($expectedStartDate, $actualStartDate);
    }

    /**
     * This test ensures that if a cancelled subscription resumed AFTER next payment date, then the startdate from today is used
     */
    public function testResumeDateIsAfterNextPaymentDate(): void
    {
        $mollieGateWay = $this->createMock(MollieGateway::class);
        $mockGateWaySubscription = $this->createMock(Subscription::class);
        $mockGateWaySubscription->startDate = '2025-08-13';
        $mockGateWaySubscription->interval = '2 months';

        $mollieGateWayJsonPayload = [];
        $mollieGateWay->method('getSubscription')->willReturn($mockGateWaySubscription);
        $mollieGateWay->method('createSubscription')->willReturnCallback(function (string $subscriptionId, array $jsonPayload) use ($mockGateWaySubscription, &$mollieGateWayJsonPayload) {
            $mollieGateWayJsonPayload = $jsonPayload;

            return $mockGateWaySubscription;
        });

        $resumeAction = $this->getAction($mollieGateWay);

        $context = Context::createDefaultContext();
        $today = \DateTime::createFromFormat('Y-m-d', '2025-10-20');
        $resumeAction->resumeSubscription('fake', $today, $context);
        $expectedStartDate = '2025-10-20';
        $actualStartDate = $mollieGateWayJsonPayload['startDate'];
        $this->assertSame($expectedStartDate, $actualStartDate);
    }

    private function getAction(MollieGateway $gateway): ResumeAction
    {
        $fakePluginSettings = new MollieSettingStruct();
        $fakePluginSettings->setSubscriptionsEnabled(true);
        $fakePluginSettings->setSubscriptionsAllowPauseResume(true);

        $settingsService = $this->createMock(SettingsService::class);
        $settingsService->method('getSettings')->willReturn($fakePluginSettings);

        $fakeSubscription = new SubscriptionEntity();
        $fakeSubscription->setId('fake');
        $fakeSubscription->setStatus(SubscriptionStatus::CANCELED);
        $fakeSubscription->setCustomer($this->createMock(CustomerEntity::class));
        $fakeSubscription->setDescription('Fake Description');
        $fakeSubscription->setMollieCustomerId('fakeCustomerId');

        $subscriptionHistoryEntries = new SubscriptionHistoryCollection();
        $subscriptionHistory = new SubscriptionHistoryEntity();
        $subscriptionHistory->setUniqueIdentifier('fake');
        $subscriptionHistory->setMollieId('sub_fakenumber');
        $subscriptionHistoryEntries->add($subscriptionHistory);

        $fakeSubscription->setHistoryEntries($subscriptionHistoryEntries);

        $subscriptionMetadata = new SubscriptionMetadata('2025-08-13', 2, IntervalUnit::MONTHS, 0, '');
        $fakeSubscription->setMetadata($subscriptionMetadata);

        $repoSubscriptions = $this->createMock(SubscriptionRepository::class);
        $repoSubscriptions->method('findById')->willReturn($fakeSubscription);

        $dataBuilder = new MollieDataBuilder(
            $this->createMock(RoutingBuilder::class),
        );

        return new ResumeAction(
            $settingsService,
            $repoSubscriptions,
            $this->createMock(SubscriptionBuilder::class),
            $dataBuilder,
            $this->createMock(CustomerService::class),
            $gateway,
            $this->createMock(CancellationValidator::class),
            $this->createMock(FlowBuilderFactory::class),
            $this->createMock(FlowBuilderEventFactory::class),
            $this->createMock(SubscriptionHistoryHandler::class),
            new NullLogger()
        );
    }
}
