<?php

namespace Kiener\MolliePayments\Tests\Service;


use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\DirectDebitPayment;
use Kiener\MolliePayments\Handler\Method\EpsPayment;
use Kiener\MolliePayments\Handler\Method\GiftCardPayment;
use Kiener\MolliePayments\Handler\Method\GiroPayPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Handler\Method\In3Payment;
use Kiener\MolliePayments\Handler\Method\KbcPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayLaterPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayNowPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaSliceItPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\PaySafeCardPayment;
use Kiener\MolliePayments\Handler\Method\Przelewy24Payment;
use Kiener\MolliePayments\Handler\Method\SofortPayment;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\Service\HttpClient\Adapter\Curl\CurlClient;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Mollie\Api\HttpAdapter\CurlMollieHttpAdapter;
use MolliePayments\Tests\Fakes\FakeEntityRepository;
use MolliePayments\Tests\Fakes\FakeHttpClient;
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
            new FakeHttpClient()
        );
    }

    /**
     * This test verifies that our list of officially supported payment
     * methods is not touched without recognizing it.
     * @return void
     */
    public function testSupportedMethods(): void
    {
        $expected = [
            ApplePayPayment::class,
            BanContactPayment::class,
            BankTransferPayment::class,
            BelfiusPayment::class,
            CreditCardPayment::class,
            EpsPayment::class,
            GiftCardPayment::class,
            GiroPayPayment::class,
            iDealPayment::class,
            KbcPayment::class,
            KlarnaPayLaterPayment::class,
            KlarnaPayNowPayment::class,
            KlarnaSliceItPayment::class,
            PayPalPayment::class,
            PaySafeCardPayment::class,
            Przelewy24Payment::class,
            SofortPayment::class,
            VoucherPayment::class,
            In3Payment::class
        ];

        $handlers = $this->paymentMethodService->getPaymentHandlers();

        $this->assertEquals($expected, $handlers);
    }

    public function setUpInstalledPaymentMethodsSearchReturn(array $paymentMethodHandlers): void
    {
        $paymentMethodIdentifier = 1;
        $paymentMethods = new EntityCollection();

        foreach ($paymentMethodHandlers as $paymentMethodHandler) {
            $paymentMethod = $this->createConfiguredMock(PaymentMethodEntity::class, [
                'getUniqueIdentifier' => (string)$paymentMethodIdentifier,
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

    public function setUpExistingPaymentMethodsSearchReturn(int $total = 0, array $ids = []): void
    {
        $num = 1;
        $methods = [];

        foreach ($ids as $id) {
            $methods[] = $this->createConfiguredMock(PaymentMethodEntity::class, [
                'getId' => $id,
                'getName' => 'Test ' . $num,
            ]);

            $num++;
        }

        $search = $this->createConfiguredMock(EntitySearchResult::class, [
            'getTotal' => $total,
            'first' => reset($methods),
            'last' => end($methods)
        ]);

        $this->paymentMethodRepository->entitySearchResults = [$search];
    }

    public function setUpPaymentMethodUpsertReturn(): void
    {
        $entityWritten = $this->createMock(EntityWrittenContainerEvent::class);

        $this->paymentMethodRepository->entityWrittenContainerEvents = [$entityWritten];
    }

    public function testHasAnArrayOfInstallableMolliePaymentMethods(): void
    {
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

        foreach ($installedPaymentMethodHandlers as $paymentMethodHandler) {
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
        $this->setUpExistingPaymentMethodsSearchReturn();
        $this->setUpPaymentMethodUpsertReturn();

        $installablePaymentMethods = $this->paymentMethodService->getInstallablePaymentMethods();
        $installablePaymentMethod = array_shift($installablePaymentMethods); // we pick one, so we don't need to fake a large number of results

        $this->paymentMethodService->addPaymentMethods([$installablePaymentMethod], $this->context);

        $actualPaymentHandler = $this->paymentMethodRepository->data[0][0]['handlerIdentifier'];
        $expectedPaymentHandler = $installablePaymentMethod['handler'];

        self::assertSame(
            $expectedPaymentHandler,
            $actualPaymentHandler,
            sprintf('The upserted data from method addPaymentMethods is expected to contain an array with handlerIdentifier "%s"', $installablePaymentMethod['handler'])
        );
    }

    public function testDoesActivatePaymentMethods(): void
    {
        $this->setUpExistingPaymentMethodsSearchReturn(1, ['112233']);
        $this->setUpPaymentMethodUpsertReturn();

        $installablePaymentMethods = $this->paymentMethodService->getInstallablePaymentMethods();
        $paymentMethod = $installablePaymentMethods[0];

        $this->paymentMethodService->activatePaymentMethods([$paymentMethod], [], $this->context);

        // We expect the upserted data to have an active field that is true
        $actualPaymentMethodActive = $this->paymentMethodRepository->data[0][0]['active'];

        self::assertTrue(
            $actualPaymentMethodActive,
            'The upserted data from method activatePaymentMethods is expected to contain active with value "true".'
        );
    }

    public function testDoesOnlyActivateNewlyInstalledPaymentMethods(): void
    {
        $paymentMethodIds = ['112233', '445566'];

        $this->setUpExistingPaymentMethodsSearchReturn(count($paymentMethodIds), $paymentMethodIds);
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

        // We expect the upserted data to contain 1 item to be activated, since 1 payment handler was already installed
        $actualNumberOfPaymentMethodsUpserted = count($this->paymentMethodRepository->data[0]);
        $expectedNumberOfPaymentMethodsUpserted = 1;

        self::assertSame(
            $expectedNumberOfPaymentMethodsUpserted,
            $actualNumberOfPaymentMethodsUpserted,
            'The upserted data is expected to have only 1 result.'
        );

        // We expect the id of the payment method in the upserted data to be the same as the handler of the newly installed payment method
        $actualPaymentMethodId = $this->paymentMethodRepository->data[0][0]['id'];
        $expectedPaymentMethodId = $paymentMethodIds[0];

        self::assertSame(
            $expectedPaymentMethodId,
            $actualPaymentMethodId,
            sprintf('The upserted data from method activatePaymentMethods is expected to contain id "%s".', $paymentMethodIds[0])
        );
    }
}
