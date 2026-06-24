<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\PaymentMethod;

use Kiener\MolliePayments\MolliePayments;
use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\TestOnlyAwareInterface;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentMethodFixture extends AbstractFixture
{
    /**
     * @param EntityRepository<SalesChannelCollection<SalesChannelEntity>> $salesChannelRepository
     * @param EntityRepository<PaymentMethodCollection<PaymentMethodEntity>> $paymentMethodRepository
     */
    public function __construct(
        private readonly PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: 'payment_method.repository')]
        private readonly EntityRepository $paymentMethodRepository,
        private readonly PluginIdProvider $pluginIdProvider,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::SETUP;
    }

    public function install(Context $context): void
    {
        $existingPaymentMethodIds = $this->getExistingPaymentMethodIds($context);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $this->installTestOnlyPaymentMethods($context, $existingPaymentMethodIds);
        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();
        $upsertData = [];
        $activePaymentMethods = $this->getActivePaymentMethods($existingPaymentMethodIds);
        foreach ($salesChannels as $salesChannel) {
            $upsertData[] = [
                'id' => $salesChannel->getId(),
                'paymentMethods' => $activePaymentMethods
            ];
        }
        $this->salesChannelRepository->upsert($upsertData, $context);
    }

    public function uninstall(Context $context): void
    {
        // We dont want to unassign payment methods
    }

    /**
     * @param array<string, string> $existingPaymentMethodIds
     */
    private function installTestOnlyPaymentMethods(Context $context, array $existingPaymentMethodIds): void
    {
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(MolliePayments::class, $context);
        $paymentHandlers = $this->paymentHandlerLocator->getPaymentMethods();
        foreach ($paymentHandlers as $paymentHandler) {
            if (! $paymentHandler instanceof TestOnlyAwareInterface) {
                continue;
            }
            $technicalName = $paymentHandler->getTechnicalName();
            $this->paymentMethodRepository->upsert([
                [
                    'id' => $existingPaymentMethodIds[$technicalName] ?? Uuid::fromStringToHex('mollie-payment-' . $technicalName),
                    'handlerIdentifier' => get_class($paymentHandler),
                    'technicalName' => $technicalName,
                    'pluginId' => $pluginId,
                    'name' => $paymentHandler->getName(),
                    'active' => true,
                    'customFields' => [
                        'mollie_payment_method_name' => $paymentHandler->getPaymentMethod()->value,
                    ],
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => $paymentHandler->getName(),
                        ],
                    ],
                ],
            ], $context);
        }
    }

    /**
     * @param array<string, string> $existingPaymentMethodIds
     *
     * @return array<mixed>
     */
    private function getActivePaymentMethods(array $existingPaymentMethodIds): array
    {
        $paymentHandlers = $this->paymentHandlerLocator->getPaymentMethods();
        $paypalExpressSettings = $this->settingsService->getPaypalExpressSettings();
        $result = [];
        foreach ($paymentHandlers as $paymentHandler) {
            if ($paymentHandler instanceof PayPalExpressPayment && ! $paypalExpressSettings->isEnabled()) {
                continue;
            }
            $technicalName = $paymentHandler->getTechnicalName();
            $paymentMethodName = $paymentHandler->getName();
            $isDeprecatedMethod = $paymentHandler instanceof DeprecatedMethodAwareInterface;
            $isTestOnlyActive = $paymentHandler instanceof TestOnlyAwareInterface;
            $result[] = [
                'id' => $existingPaymentMethodIds[$technicalName] ?? Uuid::fromStringToHex('mollie-payment-' . $technicalName),
                'technicalName' => $technicalName,
                'name' => $paymentMethodName,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => $paymentMethodName,
                    ],
                ],
                'active' => $isDeprecatedMethod === false || $isTestOnlyActive
            ];
        }

        return $result;
    }

    /**
     * @return array<string, string> map of technicalName => existing payment method id
     */
    private function getExistingPaymentMethodIds(Context $context): array
    {
        $technicalNames = [];
        foreach ($this->paymentHandlerLocator->getPaymentMethods() as $paymentHandler) {
            $technicalNames[] = $paymentHandler->getTechnicalName();
        }

        $criteria = new Criteria();
        $technicalNameFilter = new EqualsAnyFilter('technicalName', $technicalNames);
        $criteria->addFilter($technicalNameFilter);

        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context)->getEntities();

        $result = [];
        foreach ($paymentMethods as $paymentMethod) {
            $technicalName = $paymentMethod->getTechnicalName();
            if ($technicalName === null) {
                continue;
            }
            $result[$technicalName] = $paymentMethod->getId();
        }

        return $result;
    }
}
