<?php

namespace Kiener\MolliePayments\Components\Installer;

use Kiener\MolliePayments\Components\Subscription\Services\Installer\MailTemplateInstaller;
use Kiener\MolliePayments\Service\ApplePayDirect\ApplePayDomainVerificationService;
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
     * @var ApplePayDomainVerificationService
     */
    private $applePayDomainService;

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
     * @param ApplePayDomainVerificationService $applePayDomainService
     * @param MailTemplateInstaller $subscriptionMailInstaller
     */
    public function __construct(CustomFieldsInstaller $customFieldsInstaller, PaymentMethodService $paymentMethodService, ApplePayDomainVerificationService $applePayDomainService, MailTemplateInstaller $subscriptionMailInstaller)
    {
        $this->customFieldsInstaller = $customFieldsInstaller;
        $this->paymentMethodService = $paymentMethodService;
        $this->applePayDomainService = $applePayDomainService;
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

        $this->applePayDomainService->downloadDomainAssociationFile();
    }
}
