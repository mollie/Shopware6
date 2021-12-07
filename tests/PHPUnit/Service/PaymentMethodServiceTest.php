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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentMethodServiceTest extends TestCase
{
    private EntityRepositoryInterface $mediaRepository;
    private EntityRepositoryInterface $paymentMethodRepository;
    private PaymentMethodService $paymentMethodService;

    public function setUp(): void
    {
        parent::setUp();

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

    public function setUpInstalledPaymentMethods(array $paymentMethodHandlers): void
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

    public function setUpMedia(): void
    {
        $media = $this->createConfiguredMock(MediaEntity::class, [
            'getId' => '1',
        ]);

        $search = $this->createConfiguredMock(EntitySearchResult::class, [
            'count' => 1,
            'first' => $media,
        ]);

        $this->mediaRepository->entitySearchResults = [$search];
    }

    public function testHasAnArrayOfInstallableMolliePaymentMethods(): void
    {
        self::assertIsArray(
            $this->paymentMethodService->getInstallablePaymentMethods(),
            sprintf('The response of method %s is expected to be of type array.', 'getInstallablePaymentMethods')
        );

        self::assertNotEmpty(
            $this->paymentMethodService->getInstallablePaymentMethods(),
            sprintf('The response of method %s is expected to be not empty.', 'getInstallablePaymentMethods')
        );
    }

    public function testDoesFindInstalledMolliePaymentMethodsIfPresent(): void
    {
        $this->setUpInstalledPaymentMethods([
            CashPayment::class,
            ApplePayPayment::class,
            DebitPayment::class,
            iDealPayment::class,
        ]);

        $context = $this->createMock(Context::class);

        $installedPaymentMethodHandlers = $this->paymentMethodService->getInstalledPaymentMethodHandlers(
            $this->paymentMethodService->getPaymentHandlers(),
            $context
        );

        self::assertNotEmpty(
            $installedPaymentMethodHandlers,
            sprintf('The response of method %s is expected to be not empty when Mollie payment methods are installed.', 'getInstalledPaymentMethodHandlers')
        );

        $hasNonMolliePaymentMethods = false;

        foreach($installedPaymentMethodHandlers as $paymentMethodHandler) {
            if (!in_array($paymentMethodHandler, $this->paymentMethodService->getPaymentHandlers(), true)) {
                $hasNonMolliePaymentMethods = true;
            }
        }

        self::assertFalse(
            $hasNonMolliePaymentMethods,
            sprintf(
                'The response of method %s is expected not to return non-Mollie payment methods.',
                'getInstalledPaymentMethodHandlers'
            )
        );
    }

    public function testDoesNotFindInstalledMolliePaymentMethodsIfNotPresent(): void
    {
        $this->setUpInstalledPaymentMethods([
            CashPayment::class,
            DebitPayment::class,
        ]);

        $context = $this->createMock(Context::class);

        $installedPaymentMethodHandlers = $this->paymentMethodService->getInstalledPaymentMethodHandlers(
            $this->paymentMethodService->getPaymentHandlers(),
            $context
        );

        self::assertEmpty(
            $installedPaymentMethodHandlers,
            sprintf(
                'The response of method %s is expected to be empty when no Mollie payment methods are installed.',
                'getInstalledPaymentMethodHandlers'
            )
        );
    }

    public function testDoesAddPaymentMethods(): void
    {
        $this->setUpMedia();

        $context = $this->createMock(Context::class);

        $this->paymentMethodRepository->idSearchResults = [new IdSearchResult(0, [], new Criteria(), $context)];

        $installablePaymentMethods = $this->paymentMethodService->getInstallablePaymentMethods();

        $this->paymentMethodService->addPaymentMethods($installablePaymentMethods, $context);

        $upsertResult = $this->paymentMethodRepository->data;

        self::assertNotEmpty(
            $upsertResult,
            sprintf('The upserted data from method %s is expected not to be empty when providing installable payment methods.', 'addPaymentMethods')
        );
    }
}