<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer;

use Mollie\Shopware\Component\Payment\PaymentMethodInstaller;
use Shopware\Core\Framework\Context;

final class PluginInstaller
{
    public function __construct(
        private readonly CustomFieldsInstaller $customFieldsInstaller,
        private readonly PaymentMethodInstaller $paymentMethodInstaller
    ) {
    }

    public function install(Context $context): void
    {
        $this->customFieldsInstaller->install($context);
        $this->paymentMethodInstaller->install($context);
    }
}
