<?php

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\MolliePayments;
use Kiener\MolliePayments\Service\PaymentMethodService;
use MolliePayments\Tests\Fakes\FakeEntityRepository;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentMethodServiceTest extends TestCase
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentMethodService
     */
    private $paymentMethodService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->createMock(Context::class);;
        $this->mediaRepository = new FakeEntityRepository(new MediaDefinition());
        $this->paymentMethodRepository = new FakeEntityRepository(new PaymentMethodDefinition());

        $this->paymentMethodService = new PaymentMethodService(
            $this->createMock(MediaService::class),
            $this->mediaRepository,
            $this->paymentMethodRepository,
            $this->createMock(PluginIdProvider::class),
            MolliePayments::class
        );
    }

    public function setUpInstalledPaymentMethodsSearchReturn(array $paymentMethodHandlers): void
    {
        $paymentMethodIdentifier = 1;
        $paymentMethods = new EntityCollection();

        foreach ($paymentMethodHandlers as $paymentMethodHandler) {
            $paymentMethod = $this->createConfiguredMock(PaymentMethodEntity::class, [
                'getUniqueIdentifier' => (string) $paymentMethodIdentifier,
                'getHandlerIdentifier' => $paymentMethodHandler,
            ]);

            $paymentMethods->add($paymentMethod);
            $paymentMethodIdentifier++;
        }

        $search = $this->createConfiguredMock(EntitySearchResult::class, [
            'count' => count($paymentMethods),
            'getEntities' => $paymentMethods,
        ]);

        $this->paymentMethodRepository->entitySearchResults = [$search];
    }

    public function setUpMediaRepositorySearchReturn(): void
    {
        $mediaEntity = $this->createConfiguredMock(MediaEntity::class, [
            'getId' => '1',
        ]);

        $search = $this->createConfiguredMock(EntitySearchResult::class, [
            'count' => 1,
            'first' => $mediaEntity,
        ]);

        $this->mediaRepository->entitySearchResults = [$search];
    }

    public function setUpExistingPaymentMethodsSearchIdsReturn(int $total = 0, array $ids = []): void
    {
        $search = $this->createConfiguredMock(IdSearchResult::class, [
            'getTotal' => $total,
            'getIds' => $ids,
        ]);

        $this->paymentMethodRepository->idSearchResults = [$search];
    }

    public function setUpPaymentMethodUpsertReturn(): void
    {
        $entityWritten = $this->createMock(EntityWrittenContainerEvent::class);

        $this->paymentMethodRepository->entityWrittenContainerEvents = [$entityWritten];
    }

    public function testHasAnArrayOfInstallableMolliePaymentMethods(): void
    {
        self::assertIsArray(
            $this->paymentMethodService->getInstallablePaymentMethods(),
            'The response of method getInstallablePaymentMethods is expected to be of type array.'
        );

        self::assertNotEmpty(
            $this->paymentMethodService->getInstallablePaymentMethods(),
            'The response of method getInstallablePaymentMethods is expected to be not empty.'
        );
    }

    public function testDoesFindInstalledMolliePaymentMethodsIfPresent(): void
    {
        $this->setUpInstalledPaymentMethodsSearchReturn([
            CashPayment::class,
            ApplePayPayment::class,
            DebitPayment::class,
            iDealPayment::class,
        ]);

        $installedPaymentMethodHandlers = $this->paymentMethodService->getInstalledPaymentMethodHandlers(
            $this->paymentMethodService->getPaymentHandlers(),
            $this->context
        );

        self::assertNotEmpty(
            $installedPaymentMethodHandlers,
            'The response of method getInstalledPaymentMethodHandlers is expected to be not empty when Mollie payment methods are installed.'
        );

        $hasNonMolliePaymentMethods = false;

        foreach($installedPaymentMethodHandlers as $paymentMethodHandler) {
            if (!in_array($paymentMethodHandler, $this->paymentMethodService->getPaymentHandlers(), true)) {
                $hasNonMolliePaymentMethods = true;
            }
        }

        self::assertFalse(
            $hasNonMolliePaymentMethods,
            'The response of method getInstalledPaymentMethodHandlers is expected not to return non-Mollie payment methods.'
        );
    }

    public function testDoesNotFindInstalledMolliePaymentMethodsIfNotPresent(): void
    {
        $this->setUpInstalledPaymentMethodsSearchReturn([
            CashPayment::class,
            DebitPayment::class,
        ]);

        $installedPaymentMethodHandlers = $this->paymentMethodService->getInstalledPaymentMethodHandlers(
            $this->paymentMethodService->getPaymentHandlers(),
            $this->context
        );

        self::assertEmpty(
            $installedPaymentMethodHandlers,
            'The response of method getInstalledPaymentMethodHandlers is expected to be empty when no Mollie payment methods are installed.'
        );
    }

    public function testDoesAddPaymentMethods(): void
    {
        $this->setUpMediaRepositorySearchReturn();
        $this->setUpExistingPaymentMethodsSearchIdsReturn();
        $this->setUpPaymentMethodUpsertReturn();

        $installablePaymentMethods = $this->paymentMethodService->getInstallablePaymentMethods();
        $installablePaymentMethod = array_shift($installablePaymentMethods); // we pick one, so we don't need to fake a large number of results

        $this->paymentMethodService->addPaymentMethods([$installablePaymentMethod], $this->context);

        $upsertResult = $this->paymentMethodRepository->data;

        self::assertNotEmpty(
            $upsertResult,
            'The upserted data from method addPaymentMethods is expected not to be empty when providing installable payment methods.'
        );

        self::assertSame(
            $upsertResult[0][0]['handlerIdentifier'], $installablePaymentMethod['handler'],
            sprintf('The upserted data from method addPaymentMethods is expected to contain an array with handlerIdentifier "%s"', $installablePaymentMethod['handler'])
        );
    }

    public function testDoesActivatePaymentMethods(): void
    {
        $paymentMethodIds = ['112233'];

        $this->setUpExistingPaymentMethodsSearchIdsReturn(count($paymentMethodIds), $paymentMethodIds);
        $this->setUpPaymentMethodUpsertReturn();

        $installablePaymentMethods = $this->paymentMethodService->getInstallablePaymentMethods();
        $paymentMethod = $installablePaymentMethods[0];

        $this->paymentMethodService->activatePaymentMethods([$paymentMethod], [], $this->context);

        $upsertResult = $this->paymentMethodRepository->data;

        self::assertNotEmpty(
            $upsertResult,
            'The upserted data from method activatePaymentMethods is expected not to be empty when providing payment methods.'
        );

        self::assertSame(
            $upsertResult[0][0]['id'],
            $paymentMethodIds[0],
            sprintf('The upserted data from method activatePaymentMethods is expected to contain id "%s".', $paymentMethodIds[0])
        );

        self::assertTrue(
            $upsertResult[0][0]['active'],
            'The upserted data from method activatePaymentMethods is expected to contain active with value "true".'
        );
    }

    public function testDoesOnlyActivateNewlyInstalledPaymentMethods(): void
    {
        $paymentMethodIds = ['112233', '445566'];

        $this->setUpExistingPaymentMethodsSearchIdsReturn(count($paymentMethodIds), $paymentMethodIds);
        $this->setUpPaymentMethodUpsertReturn();

        $installablePaymentMethods = $this->paymentMethodService->getInstallablePaymentMethods();

        $paymentMethods = [
            $installablePaymentMethods[0],
            $installablePaymentMethods[1],
        ];

        $installedHandlers = [
            $installablePaymentMethods[1]['handler'],
        ];

        $this->paymentMethodService->activatePaymentMethods($paymentMethods, $installedHandlers, $this->context);

        $upsertResult = $this->paymentMethodRepository->data;

        self::assertCount(
            1,
            $upsertResult[0],
            'The upserted data is expected to have only 1 result.'
        );

        self::assertSame(
            $upsertResult[0][0]['id'],
            $paymentMethodIds[0],
            sprintf('The upserted data from method activatePaymentMethods is expected to contain id "%s".', $paymentMethodIds[0])
        );
    }
}
