<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\ApplePayDirectEnabled;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepository;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class IsEnabledRoute
{

    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var PaymentMethodRepository
     */
    private $repoPaymentMethods;



    /**
     * @param SettingsService $pluginSettings
     * @param PaymentMethodRepository $repoPaymentMethods
     */
    public function __construct(SettingsService $pluginSettings, PaymentMethodRepository $repoPaymentMethods)
    {
        $this->pluginSettings = $pluginSettings;
        $this->repoPaymentMethods = $repoPaymentMethods;
    }


    /**
     *
     * @param SalesChannelContext $context
     * @return ApplePayDirectEnabled
     */
    public function isApplePayDirectEnabled(SalesChannelContext $context): ApplePayDirectEnabled
    {
        $settings = $this->pluginSettings->getSettings($context->getSalesChannel()->getId());

        /** @var array|null $salesChannelPaymentIDss */
        $salesChannelPaymentIDs = $context->getSalesChannel()->getPaymentMethodIds();

        $enabled = false;

        if (is_array($salesChannelPaymentIDs) && $settings->isEnableApplePayDirect()) {

            $applePayMethodID = $this->repoPaymentMethods->getActiveApplePayID($context->getContext());

            foreach ($salesChannelPaymentIDs as $tempID) {
                # verify if our Apple Pay payment method is indeed in use
                # for the current sales channel
                if ($tempID === $applePayMethodID) {
                    $enabled = true;
                    break;
                }
            }
        }

        return new ApplePayDirectEnabled($enabled);
    }

}
