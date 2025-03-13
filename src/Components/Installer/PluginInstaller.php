<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Installer;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Components\Subscription\Services\Installer\MailTemplateInstaller;
use Kiener\MolliePayments\Service\Installer\CustomFieldsInstaller;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Shopware\Core\Framework\Context;

class PluginInstaller
{
    /**
     * @var CustomFieldsInstaller
     */
    private $customFieldsInstaller;

    /**
     * @var ApplePayDirect
     */
    private $applePayDirect;

    /**
     * @var PaymentMethodService
     */
    private $paymentMethodService;

    /**
     * @var MailTemplateInstaller
     */
    private $subscriptionMailInstaller;

    public function __construct(CustomFieldsInstaller $customFieldsInstaller, PaymentMethodService $paymentMethodService, ApplePayDirect $applePay, MailTemplateInstaller $subscriptionMailInstaller)
    {
        $this->customFieldsInstaller = $customFieldsInstaller;
        $this->paymentMethodService = $paymentMethodService;
        $this->applePayDirect = $applePay;
        $this->subscriptionMailInstaller = $subscriptionMailInstaller;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function install(Context $context): void
    {
        $this->customFieldsInstaller->install($context);

        $this->paymentMethodService->installAndActivatePaymentMethods($context);

        $this->subscriptionMailInstaller->install($context);

        $this->applePayDirect->downloadDomainAssociationFile();
    }
}
