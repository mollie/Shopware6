<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service;

use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BancomatPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\PayPalExpressPayment;
use Kiener\MolliePayments\Handler\Method\PaySafeCardPayment;
use Kiener\MolliePayments\Handler\Method\PosPayment;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Kiener\MolliePayments\Service\PayPalExpressConfig;
use MolliePayments\Shopware\Tests\Fakes\FakeHttpClient;
use MolliePayments\Shopware\Tests\Fakes\Repositories\FakeMediaRepository;
use MolliePayments\Shopware\Tests\Fakes\Repositories\FakePaymentMethodRepository;
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

    protected function setUp(): void
    {
        parent::setUp();

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('id-123');
        $paymentMethod->setHandlerIdentifier('handler-id-123');

        $this->context = $this->createMock(Context::class);

        $this->mediaRepository = new FakeMediaRepository(new MediaDefinition());
        $this->paymentMethodRepository = new FakePaymentMethodRepository($paymentMethod);

        $this->paymentMethodService = new PaymentMethodService(
            new VersionCompare('6.5.6.0'),
            $this->createMock(MediaService::class),
            $this->mediaRepository,
            $this->paymentMethodRepository,
            $this->createMock(PluginIdProvider::class),
            new FakeHttpClient(),
            new PayPalExpressConfig(1),
        );
    }

    /**
     * Starting with Shopware 6.5.7.0 a new technical name is
     * required for a payment method.
     * This test verifies that our used prefix is always the same.
     */
    public function testTechnicalPaymentMethodPrefix(): void
    {
        $this->assertEquals('payment_mollie_', PaymentMethodService::TECHNICAL_NAME_PREFIX);
    }

    /**
     * This test verifies that our list of officially supported payment
     * methods is not touched without recognizing it.
     */
    public function testSupportedMethods(): void
    {
        $expected = [
            ApplePayPayment::class,
            BankTransferPayment::class,
            PaySafeCardPayment::class,
            VoucherPayment::class,
            PosPayment::class,
            BancomatPayment::class,
            PayPalExpressPayment::class,
        ];

        $handlers = $this->paymentMethodService->getPaymentHandlers();

        $this->assertEquals($expected, $handlers);
    }
}
