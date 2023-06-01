<?php

namespace Kiener\MolliePayments\Repository\PaymentMethod;

use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $repoPaymentMethods;


    /**
     * @param EntityRepository $repoPaymentMethods
     */
    public function __construct($repoPaymentMethods)
    {
        $this->repoPaymentMethods = $repoPaymentMethods;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->repoPaymentMethods->searchIds($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->repoPaymentMethods->upsert($data, $context);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->repoPaymentMethods->search($criteria, $context);
    }

    /**
     * @param Context $context
     * @throws \Exception
     * @return string
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
