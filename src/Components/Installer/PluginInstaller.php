<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Installer;

use Kiener\MolliePayments\Service\Installer\CustomFieldsInstaller;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Mollie\Shopware\Component\Payment\PaymentMethodInstaller;
use Shopware\Core\Framework\Context;

class PluginInstaller
{
    /**
     * @var CustomFieldsInstaller
     */
    private $customFieldsInstaller;

    /**
     * @var PaymentMethodService
     */
    private $paymentMethodService;

    private PaymentMethodInstaller $paymentMethodInstaller;

    public function __construct(CustomFieldsInstaller $customFieldsInstaller,
        PaymentMethodService $paymentMethodService,
        PaymentMethodInstaller $paymentMethodInstaller)
    {
        $this->customFieldsInstaller = $customFieldsInstaller;
        $this->paymentMethodService = $paymentMethodService;
        $this->paymentMethodInstaller = $paymentMethodInstaller;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function install(Context $context): void
    {
        $this->customFieldsInstaller->install($context);

        $this->paymentMethodService->installAndActivatePaymentMethods($context);
        $this->paymentMethodInstaller->install($context);
    }
}
