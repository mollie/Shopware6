<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Payment\PaymentMethodInstaller;
use Mollie\Shopware\Component\Settings\Struct\PayPalExpressSettings;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeDeprecatedPaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeFileFetcher;
use Mollie\Shopware\Unit\Payment\Fake\FakeFinalize;
use Mollie\Shopware\Unit\Payment\Fake\FakeMediaService;
use Mollie\Shopware\Unit\Payment\Fake\FakePay;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePluginIdProvider;
use Mollie\Shopware\Unit\Payment\Fake\FakeTestOnlyPaymentHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(PaymentMethodInstaller::class)]
final class PaymentMethodInstallerTest extends TestCase
{
    private FakeEntityRepository $paymentMethodRepository;

    private FakeEntityRepository $mediaRepository;

    private FakeMediaService $mediaService;

    private FakeFileFetcher $fileFetcher;

    private FakeLogger $logger;

    private Context $context;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = new FakeEntityRepository(new PaymentMethodDefinition());
        $this->mediaRepository = new FakeEntityRepository(new MediaDefinition());
        $this->mediaService = new FakeMediaService();
        $this->fileFetcher = new FakeFileFetcher();
        $this->logger = new FakeLogger();
        $this->context = Context::createDefaultContext();
    }

    public function testEveryMethodIsUpsertedWithItsHandlerAndTechnicalName(): void
    {
        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')]);

        $this->assertCount(1, $upserted);
        $this->assertSame('payment_mollie_ideal', $upserted[0]['technicalName']);
        $this->assertSame(FakePaymentMethodHandler::class, $upserted[0]['handlerIdentifier']);
        $this->assertSame('iDEAL', $upserted[0]['name']);
        $this->assertSame('plugin-1', $upserted[0]['pluginId']);
    }

    public function testMethodIdIsDerivedFromTheTechnicalNameSoAReinstallUpdatesInsteadOfDuplicating(): void
    {
        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')]);

        $this->assertSame(Uuid::fromStringToHex('mollie-payment-payment_mollie_ideal'), $upserted[0]['id']);
    }

    public function testMollieMethodNameIsStoredInCustomFieldsForTheCheckout(): void
    {
        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')]);

        $this->assertSame('ideal', $upserted[0]['customFields']['mollie_payment_method_name']);
    }

    public function testANewMethodIsInstalledActive(): void
    {
        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')]);

        $this->assertTrue($upserted[0]['active']);
    }

    public function testADeprecatedMethodStaysInTheShopButIsSwitchedOff(): void
    {
        $upserted = $this->install([new FakeDeprecatedPaymentHandler()]);

        $this->assertCount(1, $upserted);
        $this->assertFalse($upserted[0]['active']);
    }

    public function testATestOnlyMethodIsNeverInstalledInAShop(): void
    {
        $upserted = $this->install([
            new FakeTestOnlyPaymentHandler(),
            new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL'),
        ]);

        $this->assertCount(1, $upserted);
        $this->assertSame('payment_mollie_ideal', $upserted[0]['technicalName']);
    }

    public function testPaypalExpressIsNotInstalledWhileTheBetaIsOff(): void
    {
        $upserted = $this->install([$this->paypalExpressHandler()]);

        $this->assertCount(0, $upserted);
    }

    public function testPaypalExpressIsInstalledOnceTheBetaIsOn(): void
    {
        $upserted = $this->install([$this->paypalExpressHandler()], paypalExpressEnabled: true);

        $this->assertCount(1, $upserted);
        $this->assertSame('payment_mollie_paypalexpress', $upserted[0]['technicalName']);
    }

    public function testTheNameAMerchantEditedIsNotOverwrittenOnReinstall(): void
    {
        $existing = $this->existingPaymentMethod(FakePaymentMethodHandler::class, 'iDEAL (my shop)', true);

        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')], existingMethods: [$existing]);

        $this->assertSame('iDEAL (my shop)', $upserted[0]['name']);
        $this->assertSame('iDEAL (my shop)', $upserted[0]['translations'][Defaults::LANGUAGE_SYSTEM]['name']);
    }

    public function testAMethodTheMerchantDeactivatedStaysDeactivated(): void
    {
        $existing = $this->existingPaymentMethod(FakePaymentMethodHandler::class, 'iDEAL', false);

        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')], existingMethods: [$existing]);

        $this->assertFalse($upserted[0]['active']);
    }

    public function testADeprecatedMethodIsSwitchedOffEvenWhenTheMerchantHadItActive(): void
    {
        $existing = $this->existingPaymentMethod(FakeDeprecatedPaymentHandler::class, 'Belfius', true);

        $upserted = $this->install([new FakeDeprecatedPaymentHandler()], existingMethods: [$existing]);

        $this->assertFalse($upserted[0]['active']);
    }

    public function testAnEmptyNameFallsBackToTheHandlerNameSoNoMethodLosesItsLabel(): void
    {
        $existing = $this->existingPaymentMethod(FakePaymentMethodHandler::class, '', true);

        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')], existingMethods: [$existing]);

        $this->assertSame('iDEAL', $upserted[0]['name']);
    }

    public function testAnAlreadyKnownIconIsReusedInsteadOfDownloadedAgain(): void
    {
        $media = new MediaEntity();
        $media->setId('media-existing');
        $media->setFileName('ideal-icon');

        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')], existingMedia: [$media]);

        $this->assertSame('media-existing', $upserted[0]['mediaId']);
        $this->assertCount(0, $this->fileFetcher->requestedUrls);
    }

    public function testAnUnknownIconIsDownloadedFromMollieAndAttached(): void
    {
        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')]);

        $this->assertSame('media-1', $upserted[0]['mediaId']);
        $this->assertSame(['ideal-icon'], $this->mediaService->savedFileNames);
    }

    public function testTheIconIsFetchedFromTheMollieIconUrl(): void
    {
        $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')]);

        $this->assertSame('https://www.mollie.com/external/icons/payment-methods/ideal.svg', $this->fileFetcher->requestedUrls[0]);
    }

    public function testAMethodWithoutAReachableIconIsStillInstalled(): void
    {
        $this->fileFetcher = new FakeFileFetcher(iconAvailable: false);

        $upserted = $this->install([new FakePaymentMethodHandler(PaymentMethod::IDEAL, 'iDEAL')]);

        $this->assertCount(1, $upserted);
        $this->assertArrayNotHasKey('mediaId', $upserted[0]);
    }

    /**
     * @param list<AbstractMolliePaymentHandler> $handlers
     * @param list<PaymentMethodEntity> $existingMethods
     * @param list<MediaEntity> $existingMedia
     *
     * @return array<array<string, mixed>>
     */
    private function install(array $handlers, bool $paypalExpressEnabled = false, array $existingMethods = [], array $existingMedia = []): array
    {
        $this->mediaRepository->entitySearchResults[] = new EntitySearchResult(
            'media',
            count($existingMedia),
            new MediaCollection($existingMedia),
            null,
            new Criteria(),
            $this->context
        );
        $this->paymentMethodRepository->entitySearchResults[] = new EntitySearchResult(
            'payment_method',
            count($existingMethods),
            new PaymentMethodCollection($existingMethods),
            null,
            new Criteria(),
            $this->context
        );
        $this->paymentMethodRepository->entityWrittenContainerEvents[] = new EntityWrittenContainerEvent(
            $this->context,
            new NestedEventCollection(),
            []
        );

        $installer = new PaymentMethodInstaller(
            new PaymentHandlerLocator($handlers),
            $this->paymentMethodRepository,
            $this->mediaRepository,
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings($paypalExpressEnabled)),
            $this->mediaService,
            $this->fileFetcher,
            new FakePluginIdProvider(),
            $this->logger
        );

        $installer->install($this->context);

        return $this->paymentMethodRepository->data[0];
    }

    private function paypalExpressHandler(): PayPalExpressPayment
    {
        return new PayPalExpressPayment(new FakePay(), new FakeFinalize(), $this->logger);
    }

    private function existingPaymentMethod(string $handlerIdentifier, string $name, bool $active): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('existing-payment-method');
        $paymentMethod->setHandlerIdentifier($handlerIdentifier);
        $paymentMethod->setName($name);
        $paymentMethod->setActive($active);
        $paymentMethod->setAfterOrderEnabled(true);
        $paymentMethod->setTechnicalName('payment_mollie_ideal');

        return $paymentMethod;
    }
}
