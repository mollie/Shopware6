<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Shipment;

use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Kiener\MolliePayments\Components\Fixtures\Utils\CurrencyUtils;
use Kiener\MolliePayments\Components\Fixtures\Utils\DeliveryTimesUtils;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class ShipmentFixture implements MollieFixtureHandlerInterface
{
    private const SHIPMENT_ID = '0d1eeedd6d22436385580e2ff42431b9';

    /**
     * @var EntityRepository<ShippingMethodCollection>
     */
    private $repoShipments;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private $repoSalesChannels;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private $repoRules;

    private CurrencyUtils $currencyUtils;

    private DeliveryTimesUtils $deliveryTimesUtils;

    /**
     * @param EntityRepository<ShippingMethodCollection> $repoShipments
     * @param EntityRepository<SalesChannelCollection> $repoSalesChannels
     * @param EntityRepository<RuleCollection> $repoRules
     */
    public function __construct($repoShipments, $repoSalesChannels, $repoRules, CurrencyUtils $currencyUtils, DeliveryTimesUtils $deliveryTimesUtils)
    {
        $this->repoShipments = $repoShipments;
        $this->repoSalesChannels = $repoSalesChannels;
        $this->repoRules = $repoRules;
        $this->currencyUtils = $currencyUtils;
        $this->deliveryTimesUtils = $deliveryTimesUtils;
    }

    public function install(): void
    {
        $ctx = Context::createDefaultContext();

        // load all-customer rules
        // because in old shopware versions this had to be assigned, NULL was not possible as ruleId
        $allCustomersRule = $this->getAllCustomersRule($ctx);

        $ruleId = ($allCustomersRule instanceof RuleEntity) ? $allCustomersRule->getId() : null;

        $this->createShipment(self::SHIPMENT_ID, 'Mollie Test Shipment', $ruleId, $ctx);
    }

    public function uninstall(): void
    {
        $ctx = Context::createDefaultContext();

        $this->repoShipments->delete([['id' => self::SHIPMENT_ID]], $ctx);
    }

    private function createShipment(string $id, string $name, ?string $ruleId, Context $context): void
    {
        $currencyEuro = $this->currencyUtils->getCurrency('EUR');
        $deliveryTime = $this->deliveryTimesUtils->getRandomDeliveryTime();

        $this->repoShipments->upsert([
            [
                'id' => $id,
                'active' => true,
                'name' => $name,
                'availabilityRuleId' => $ruleId,
                'technicalName' => 'mollie_fixture_shipment',
                'deliveryTimeId' => $deliveryTime->getId(),
                'prices' => [
                    [
                        'id' => '021eeedd6d22436385580e2ff42431b3',
                        'calculation' => 2,
                        'quantityStart' => 0,
                        'currencyPrice' => [
                            [
                                'currencyId' => $currencyEuro->getId(),
                                'net' => 4.19,
                                'gross' => 4.99,
                                'linked' => false
                            ]
                        ]
                    ]
                ],
                'translations' => [
                    'de-DE' => [
                        'trackingUrl' => 'https://www.carrier.com/de/tracking/%s'
                    ],
                    'en-GB' => [
                        'trackingUrl' => 'https://www.carrier.com/en/tracking/%s'
                    ]
                ]
            ],
        ], $context);

        $salesChannelIds = $this->repoSalesChannels->searchIds(new Criteria(), $context)->getIds();

        $this->assignShippingMethod($id, $salesChannelIds, $context);
    }

    /**
     * @param array<mixed> $salesChannelIds
     */
    private function assignShippingMethod(string $shippingId, array $salesChannelIds, Context $ctx): void
    {
        $paymentUpdates = [];
        $scShippingIds = [];

        $scShippingIds[] = [
            'id' => $shippingId,
        ];

        foreach ($salesChannelIds as $id) {
            $paymentUpdates[] = [
                'id' => $id,
                'shippingMethods' => $scShippingIds,
            ];
        }

        $this->repoSalesChannels->update($paymentUpdates, $ctx);
    }

    private function getAllCustomersRule(Context $ctx): ?RuleEntity
    {
        $allRules = $this->repoRules->search(new Criteria(), $ctx);

        /** @var RuleEntity $rule */
        foreach ($allRules->getElements() as $rule) {
            $name = $rule->getName();

            if ($name === 'All customers' || str_contains($name, 'Alle Kunden')) {
                return $rule;
            }
        }

        return null;
    }
}
