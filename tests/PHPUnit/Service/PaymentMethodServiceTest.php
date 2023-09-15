<?php

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
use Kiener\MolliePayments\Handler\Method\BilliePayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
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

        $this->context = $this->createMock(Context::class);
        ;
        $this->mediaRepository = new FakeMediaRepository(new MediaDefinition());
        $this->paymentMethodRepository = new FakePaymentMethodRepository($paymentMethod);

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
            BilliePayment::class,
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
}
