<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\SalesChannel;

use Kiener\MolliePayments\Exception\SalesChannelPaymentMethodsException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelDataExtractor
{
    /**
     * @param SalesChannelEntity $salesChannelEntity
     * @return PaymentMethodCollection
     */
    public function extractPaymentMethods(SalesChannelEntity $salesChannelEntity): PaymentMethodCollection
    {
        $paymentMethods = $salesChannelEntity->getPaymentMethods();

        if ($paymentMethods instanceof PaymentMethodCollection) {
            return $paymentMethods;
        }

        throw new SalesChannelPaymentMethodsException((string)$salesChannelEntity->getName());
    }
}
