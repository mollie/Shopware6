<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service;

use Kiener\MolliePayments\Service\PaymentMethodService;
use MolliePayments\Shopware\Tests\Fakes\Repositories\FakeMediaRepository;
use MolliePayments\Shopware\Tests\Fakes\Repositories\FakePaymentMethodRepository;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;

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
            $this->paymentMethodRepository,
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
}
