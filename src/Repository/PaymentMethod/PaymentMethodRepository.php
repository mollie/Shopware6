<?php

namespace Kiener\MolliePayments\Repository\PaymentMethod;


use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PaymentMethodRepository
{

    /**
     * @var EntityRepositoryInterface
     */
    private $repoPaymentMethods;


    /**
     * @param EntityRepositoryInterface $repoPaymentMethods
     */
    public function __construct(EntityRepositoryInterface $repoPaymentMethods)
    {
        $this->repoPaymentMethods = $repoPaymentMethods;
    }

    /**
     * @param Context $context
     * @return string
     * @throws \Exception
     */
    public function getActiveApplePayID(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', ApplePayPayment::class));
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var array<string> $paymentMethods */
        $paymentMethods = $this->repoPaymentMethods->searchIds($criteria, $context)->getIds();

        if (count($paymentMethods) <= 0) {
            throw new \Exception('Payment Method Apple Pay Direct not found in system');
        }

        return (string)$paymentMethods[0];
    }

}
