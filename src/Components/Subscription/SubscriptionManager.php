<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription;

use Kiener\MolliePayments\Components\Subscription\Actions\UpdatePaymentAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\Context;

class SubscriptionManager
{
    /**
     * @var UpdatePaymentAction
     */
    private $actionUpdatePayment;

    /**
     * @var SubscriptionRepository
     */
    private $repoSubscriptions;

    public function __construct(UpdatePaymentAction $actionUpdatePayment, SubscriptionRepository $repoSubscriptions)
    {
        $this->actionUpdatePayment = $actionUpdatePayment;
        $this->repoSubscriptions = $repoSubscriptions;
    }

    /**
     * @throws \Exception
     */
    public function findSubscription(string $id, Context $context): SubscriptionEntity
    {
        try {
            return $this->repoSubscriptions->findById($id, $context);
        } catch (\Throwable $ex) {
            throw new \Exception('Subscription with ID ' . $id . ' not found in Shopware');
        }
    }

    /**
     * @throws \Exception
     */
    public function findSubscriptionByMandateId(string $customerId, string $mandateId, Context $context): SubscriptionCollection
    {
        try {
            return $this->repoSubscriptions->findByMandateId($customerId, $mandateId, $context);
        } catch (\Throwable $ex) {
            throw new \Exception('Subscription with mandate ID ' . $mandateId . ' not found in Shopware');
        }
    }

    /**
     * @throws CustomerCouldNotBeFoundException
     */
    public function updatePaymentMethodStart(string $subscriptionId, string $redirectUrl, Context $context): string
    {
        return $this->actionUpdatePayment->updatePaymentMethodStart($subscriptionId, $redirectUrl, $context);
    }

    /**
     * @throws \Exception
     */
    public function updatePaymentMethodConfirm(string $subscriptionId, Context $context): void
    {
        $this->actionUpdatePayment->updatePaymentMethodConfirm($subscriptionId, $context);
    }
}
