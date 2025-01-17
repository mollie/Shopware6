<?php

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


    /**
     * @param CustomFieldsInstaller $customFieldsInstaller
     * @param PaymentMethodService $paymentMethodService
     * @param ApplePayDirect $applePay
     * @param MailTemplateInstaller $subscriptionMailInstaller
     */
    public function __construct(CustomFieldsInstaller $customFieldsInstaller, PaymentMethodService $paymentMethodService, ApplePayDirect $applePay, MailTemplateInstaller $subscriptionMailInstaller)
    {
        $this->customFieldsInstaller = $customFieldsInstaller;
        $this->paymentMethodService = $paymentMethodService;
        $this->applePayDirect = $applePay;
        $this->subscriptionMailInstaller = $subscriptionMailInstaller;
    }

    /**
     * @param Context $context
     * @throws \Doctrine\DBAL\Exception
     * @return void
     */
    public function install(Context $context): void
    {
        $this->customFieldsInstaller->install($context);

        $this->paymentMethodService->installAndActivatePaymentMethods($context);

        $this->subscriptionMailInstaller->install($context);

        $this->applePayDirect->downloadDomainAssociationFile();
    }
}
