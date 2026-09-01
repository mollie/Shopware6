<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Payment\Fake\FakePluginIdProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(PaymentMethodRepository::class)]
final class PaymentMethodRepositoryTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private FakeEntityRepository $paymentMethodRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = new FakeEntityRepository(new PaymentMethodDefinition());
        $this->context = new Context(new SystemSource());
    }

    public function testIdIsFoundByPaymentHandler(): void
    {
        $this->paymentMethodRepository->idSearchResults[] = $this->makeIdSearchResult('creditcard-id');

        $id = $this->makeRepository()->getIdByPaymentHandler('Some\Payment\Handler', self::SALES_CHANNEL_ID, $this->context);

        $this->assertSame('creditcard-id', $id);
    }

    public function testUnknownPaymentHandlerHasNoId(): void
    {
        $this->paymentMethodRepository->idSearchResults[] = $this->makeIdSearchResult();

        $id = $this->makeRepository()->getIdByPaymentHandler('Some\Payment\Handler', self::SALES_CHANNEL_ID, $this->context);

        $this->assertNull($id);
    }

    public function testLookupIsLimitedToActiveMethodsOfTheSalesChannel(): void
    {
        $this->paymentMethodRepository->idSearchResults[] = $this->makeIdSearchResult('creditcard-id');

        $this->makeRepository()->getIdByPaymentHandler('Some\Payment\Handler', self::SALES_CHANNEL_ID, $this->context);

        $criteria = $this->paymentMethodRepository->criteria[0];

        $this->assertSame(1, $criteria->getLimit());
        $this->assertSame(
            ['active' => true, 'salesChannels.id' => self::SALES_CHANNEL_ID, 'handlerIdentifier' => 'Some\Payment\Handler'],
            $this->equalsFilters($criteria)
        );
    }

    public function testIdIsFoundByTheMollieTechnicalName(): void
    {
        $this->paymentMethodRepository->idSearchResults[] = $this->makeIdSearchResult('paypal-id');

        $id = $this->makeRepository()->getIdByPaymentMethod(PaymentMethod::PAYPAL, self::SALES_CHANNEL_ID, $this->context);

        $this->assertSame('paypal-id', $id);
        $this->assertSame('payment_mollie_paypal', $this->equalsFilters($this->paymentMethodRepository->criteria[0])['technicalName']);
    }

    public function testUnknownMolliePaymentMethodHasNoId(): void
    {
        $this->paymentMethodRepository->idSearchResults[] = $this->makeIdSearchResult();

        $this->assertNull($this->makeRepository()->getIdByPaymentMethod(PaymentMethod::PAYPAL, self::SALES_CHANNEL_ID, $this->context));
    }

    public function testAllMollieMethodsAreLookedUpByThePluginId(): void
    {
        $methods = new PaymentMethodCollection([PaymentMethodBuilder::create()->withId('paypal-id')->build()]);
        $this->paymentMethodRepository->entitySearchResults[] = new EntitySearchResult(
            PaymentMethodDefinition::ENTITY_NAME,
            $methods->count(),
            $methods,
            null,
            new Criteria(),
            $this->context
        );

        $result = $this->makeRepository()->findAllMollieMethods($this->context);

        $this->assertCount(1, $result);
        $this->assertSame(['pluginId' => 'mollie-plugin-id'], $this->equalsFilters($this->paymentMethodRepository->criteria[0]));
    }

    private function makeRepository(): PaymentMethodRepository
    {
        return new PaymentMethodRepository($this->paymentMethodRepository, new FakePluginIdProvider('mollie-plugin-id'));
    }

    private function makeIdSearchResult(?string $id = null): IdSearchResult
    {
        $data = $id === null ? [] : [['data' => ['id' => $id], 'primaryKey' => $id]];

        return new IdSearchResult(count($data), $data, new Criteria(), $this->context);
    }

    /**
     * @return array<string, mixed>
     */
    private function equalsFilters(Criteria $criteria): array
    {
        $filters = [];
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter) {
                $filters[$filter->getField()] = $filter->getValue();
            }
        }

        return $filters;
    }
}
