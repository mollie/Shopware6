<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class BizumPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BIZUM;
    }

    public function getName(): string
    {
        return 'Bizum';
    }

    public function getDescription(): string
    {
        return '​Bizum is a widely used mobile payment solution in Spain, trusted by over 25 million consumers. It offers a fast, secure, and convenient way for users to pay directly from their mobile devices, enhancing the checkout experience for both businesses and customers. By enabling Bizum through Mollie, you can tap into this popular local payment method to boost customer satisfaction and increase conversions.';
    }
}
