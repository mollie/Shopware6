<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\ApplePayDirectID;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class PaymentIdRoute
{

    /**
     * @var PaymentMethodRepository
     */
    private $repoPaymentMethods;


    /**
     * @param PaymentMethodRepository $repoPaymentMethods
     */
    public function __construct(PaymentMethodRepository $repoPaymentMethods)
    {
        $this->repoPaymentMethods = $repoPaymentMethods;
    }

    /**
     * Gets the ID of the Apple Pay payment method.
     * We need this in the storefront for some selectors in use cases like
     * hiding the payment method if it's not available in the browser.
     *
     * ATTENTION:
     * this is not about Apple Pay Direct - but the namespace of the URL is a good one (/apple-pay)
     * and I don't want to create all kinds of new controllers
     *
     * @param SalesChannelContext $context
     * @return ApplePayDirectID
     */
    public function getApplePayID(SalesChannelContext $context): ApplePayDirectID
    {
        $id = $this->repoPaymentMethods->getActiveApplePayID($context->getContext());

        return new ApplePayDirectID($id);
    }

}
