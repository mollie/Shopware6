<?php

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Handler\Method\AlmaPayment;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BancomatPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
use Kiener\MolliePayments\Handler\Method\BilliePayment;
use Kiener\MolliePayments\Handler\Method\BlikPayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\EpsPayment;
use Kiener\MolliePayments\Handler\Method\GiftCardPayment;
use Kiener\MolliePayments\Handler\Method\GiroPayPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Handler\Method\In3Payment;
use Kiener\MolliePayments\Handler\Method\KbcPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaOnePayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayLaterPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaPayNowPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaSliceItPayment;
use Kiener\MolliePayments\Handler\Method\MyBankPayment;
use Kiener\MolliePayments\Handler\Method\PayconiqPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\PaySafeCardPayment;
use Kiener\MolliePayments\Handler\Method\PosPayment;
use Kiener\MolliePayments\Handler\Method\Przelewy24Payment;
use Kiener\MolliePayments\Handler\Method\RivertyPayment;
use Kiener\MolliePayments\Handler\Method\SatispayPayment;
use Kiener\MolliePayments\Handler\Method\SofortPayment;
use Kiener\MolliePayments\Handler\Method\TrustlyPayment;
use Kiener\MolliePayments\Handler\Method\TwintPayment;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\Repository\Media\MediaRepositoryInterface;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use Kiener\MolliePayments\Service\PaymentMethodService;
use MolliePayments\Tests\Fakes\FakeHttpClient;
use MolliePayments\Tests\Fakes\Repositories\FakeMediaRepository;
use MolliePayments\Tests\Fakes\Repositories\FakePaymentMethodRepository;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentMethodServiceTest extends TestCase
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var MediaRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentMethodService
     */
    private $paymentMethodService;


    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('id-123');
        $paymentMethod->setHandlerIdentifier('handler-id-123');

        $this->context = $this->createMock(Context::class);;
        $this->mediaRepository = new FakeMediaRepository(new MediaDefinition());
        $this->paymentMethodRepository = new FakePaymentMethodRepository($paymentMethod);

        $this->paymentMethodService = new PaymentMethodService(
            '6.5.6.0',
            $this->createMock(MediaService::class),
            $this->mediaRepository,
            $this->paymentMethodRepository,
            $this->createMock(PluginIdProvider::class),
            new FakeHttpClient()
        );
    }

    /**
     * Starting with Shopware 6.5.7.0 a new technical name is
     * required for a payment method.
     * This test verifies that our used prefix is always the same.
     *
     * @return void
     */
    public function testTechnicalPaymentMethodPrefix(): void
    {
        $this->assertEquals('payment_mollie_', PaymentMethodService::TECHNICAL_NAME_PREFIX);
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
            BilliePayment::class,
            BelfiusPayment::class,
            CreditCardPayment::class,
            EpsPayment::class,
            GiftCardPayment::class,
            iDealPayment::class,
            KbcPayment::class,
            KlarnaPayLaterPayment::class,
            KlarnaPayNowPayment::class,
            KlarnaSliceItPayment::class,
            KlarnaOnePayment::class,
            PayPalPayment::class,
            PaySafeCardPayment::class,
            Przelewy24Payment::class,
            SofortPayment::class,
            VoucherPayment::class,
            In3Payment::class,
            PosPayment::class,
            TwintPayment::class,
            BlikPayment::class,
            BancomatPayment::class,
            MyBankPayment::class,
            AlmaPayment::class,
            TrustlyPayment::class,
            PayconiqPayment::class,
            RivertyPayment::class,
            SatispayPayment::class,
        ];

        $handlers = $this->paymentMethodService->getPaymentHandlers();

        $this->assertEquals($expected, $handlers);
    }
}
