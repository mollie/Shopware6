<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\PaymentMethod;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentMethodFixture extends AbstractFixture
{
    public function __construct(
        private readonly PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository      $salesChannelRepository,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService        $settingsService,
    )
    {

    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::SETUP;
    }

    public function install(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();
        $upsertData = [];
        $activePaymentMethods = $this->getActivePaymentMethods();
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
        //We dont want to unassign payment methods
    }

    private function getActivePaymentMethods(): array
    {
        $paymentHandlers = $this->paymentHandlerLocator->getPaymentMethods();
        $paypalExpressSettings = $this->settingsService->getPaypalExpressSettings();
        $result = [];
        foreach ($paymentHandlers as $paymentHandler) {

            if ($paymentHandler instanceof PayPalExpressPayment && !$paypalExpressSettings->isEnabled()) {
                continue;
            }
            $paymentMethodName = $paymentHandler->getName();
            $isDeprecatedMethod = $paymentHandler instanceof DeprecatedMethodAwareInterface;
            $result[] = [
                'id' => Uuid::fromStringToHex('mollie-payment-' . $paymentHandler->getTechnicalName()),
                'name' => $paymentMethodName,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => $paymentMethodName,
                    ],
                ],
                'active' => $isDeprecatedMethod === false
            ];
        }
        return $result;
    }
}