<?php

namespace Kiener\MolliePayments\Components\Subscription;

use Exception;
use Kiener\MolliePayments\Components\Subscription\Actions\CancelAction;
use Kiener\MolliePayments\Components\Subscription\Actions\ConfirmAction;
use Kiener\MolliePayments\Components\Subscription\Actions\CreateAction;
use Kiener\MolliePayments\Components\Subscription\Actions\PauseAction;
use Kiener\MolliePayments\Components\Subscription\Actions\RemindAction;
use Kiener\MolliePayments\Components\Subscription\Actions\RenewAction;
use Kiener\MolliePayments\Components\Subscription\Actions\ResumeAction;
use Kiener\MolliePayments\Components\Subscription\Actions\SkipAction;
use Kiener\MolliePayments\Components\Subscription\Actions\UpdateAddressAction;
use Kiener\MolliePayments\Components\Subscription\Actions\UpdatePaymentAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubscriptionManager implements SubscriptionManagerInterface
{
    /**
     * @var CreateAction
     */
    private $actionCreate;

    /**
     * @var ConfirmAction
     */
    private $actionConfirm;

    /**
     * @var UpdateAddressAction
     */
    private $actionUpdateAddress;

    /**
     * @var UpdatePaymentAction
     */
    private $actionUpdatePayment;

    /**
     * @var RenewAction
     */
    private $actionRenew;

    /**
     * @var PauseAction
     */
    private $actionPause;

    /**
     * @var ResumeAction
     */
    private $actionResume;

    /**
     * @var SkipAction
     */
    private $actionSkip;

    /**
     * @var CancelAction
     */
    private $actionCancel;

    /**
     * @var RemindAction
     */
    private $actionRemind;

    /**
     * @var SubscriptionRepository
     */
    private $repoSubscriptions;


    /**
     * @param CreateAction $actionCreate
     * @param ConfirmAction $actionConfirm
     * @param UpdateAddressAction $actionUpdateAddress
     * @param UpdatePaymentAction $actionUpdatePayment
     * @param RenewAction $actionRenew
     * @param PauseAction $actionPause
     * @param ResumeAction $actionResume
     * @param SkipAction $actionSkip
     * @param CancelAction $actionCancel
     * @param RemindAction $actionRemind
     * @param SubscriptionRepository $repoSubscriptions
     */
    public function __construct(CreateAction $actionCreate, ConfirmAction $actionConfirm, UpdateAddressAction $actionUpdateAddress, UpdatePaymentAction $actionUpdatePayment, RenewAction $actionRenew, PauseAction $actionPause, ResumeAction $actionResume, SkipAction $actionSkip, CancelAction $actionCancel, RemindAction $actionRemind, SubscriptionRepository $repoSubscriptions)
    {
        $this->actionCreate = $actionCreate;
        $this->actionConfirm = $actionConfirm;
        $this->actionUpdateAddress = $actionUpdateAddress;
        $this->actionUpdatePayment = $actionUpdatePayment;
        $this->actionRenew = $actionRenew;
        $this->actionPause = $actionPause;
        $this->actionResume = $actionResume;
        $this->actionSkip = $actionSkip;
        $this->actionCancel = $actionCancel;
        $this->actionRemind = $actionRemind;
        $this->repoSubscriptions = $repoSubscriptions;
    }


    /**
     * @param string $id
     * @param Context $context
     * @throws Exception
     * @return SubscriptionEntity
     */
    public function findSubscription(string $id, Context $context): SubscriptionEntity
    {
        try {
            return $this->repoSubscriptions->findById($id, $context);
        } catch (\Throwable $ex) {
            throw new Exception('Subscription with ID ' . $id . ' not found in Shopware');
        }
    }

    /**
     * @param string $customerId
     * @param string $mandateId
     * @param Context $context
     * @throws Exception
     * @return SubscriptionCollection
     */
    public function findSubscriptionByMandateId(string $customerId, string $mandateId, Context $context): SubscriptionCollection
    {
        try {
            return $this->repoSubscriptions->findByMandateId($customerId, $mandateId, $context);
        } catch (\Throwable $ex) {
            throw new Exception('Subscription with mandate ID ' . $mandateId . ' not found in Shopware');
        }
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @throws Exception
     * @return string
     */
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string
    {
        return $this->actionCreate->createSubscription($order, $context);
    }

    /**
     * @param OrderEntity $order
     * @param string $mandateId
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return void
     */
    public function confirmSubscription(OrderEntity $order, string $mandateId, Context $context): void
    {
        $this->actionConfirm->confirmSubscription($order, $mandateId, $context);
    }

    /**
     * @param Context $context
     * @throws Exception
     * @return int
     */
    public function remindSubscriptionRenewal(Context $context): int
    {
        return $this->actionRemind->remindSubscriptionRenewal($context);
    }

    /**
     * @param string $swSubscriptionId
     * @param string $molliePaymentId
     * @param Context $context
     * @throws Exception
     * @return OrderEntity
     */
    public function renewSubscription(string $swSubscriptionId, string $molliePaymentId, Context $context): OrderEntity
    {
        return $this->actionRenew->renewSubscription($swSubscriptionId, $molliePaymentId, $context);
    }

    /**
     * @param string $subscriptionId
     * @param string $salutationId
     * @param string $title
     * @param string $firstname
     * @param string $lastname
     * @param string $company
     * @param string $department
     * @param string $additional1
     * @param string $additional2
     * @param string $phoneNumber
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param string $countryStateId
     * @param Context $context
     * @throws Exception
     */
    public function updateBillingAddress(string $subscriptionId, string $salutationId, string $title, string $firstname, string $lastname, string $company, string $department, string $additional1, string $additional2, string $phoneNumber, string $street, string $zipcode, string $city, string $countryStateId, Context $context): void
    {
        $this->actionUpdateAddress->updateBillingAddress(
            $subscriptionId,
            $salutationId,
            $title,
            $firstname,
            $lastname,
            $company,
            $department,
            $additional1,
            $additional2,
            $phoneNumber,
            $street,
            $zipcode,
            $city,
            $countryStateId,
            $context
        );
    }

    /**
     * @param string $subscriptionId
     * @param string $salutationId
     * @param string $title
     * @param string $firstname
     * @param string $lastname
     * @param string $company
     * @param string $department
     * @param string $additional1
     * @param string $additional2
     * @param string $phoneNumber
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param string $countryStateId
     * @param Context $context
     * @throws Exception
     */
    public function updateShippingAddress(string $subscriptionId, string $salutationId, string $title, string $firstname, string $lastname, string $company, string $department, string $additional1, string $additional2, string $phoneNumber, string $street, string $zipcode, string $city, string $countryStateId, Context $context): void
    {
        $this->actionUpdateAddress->updateShippingAddress(
            $subscriptionId,
            $salutationId,
            $title,
            $firstname,
            $lastname,
            $company,
            $department,
            $additional1,
            $additional2,
            $phoneNumber,
            $street,
            $zipcode,
            $city,
            $countryStateId,
            $context
        );
    }

    /**
     * @param string $subscriptionId
     * @param string $redirectUrl
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return string
     */
    public function updatePaymentMethodStart(string $subscriptionId, string $redirectUrl, Context $context): string
    {
        return $this->actionUpdatePayment->updatePaymentMethodStart($subscriptionId, $redirectUrl, $context);
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function updatePaymentMethodConfirm(string $subscriptionId, Context $context): void
    {
        $this->actionUpdatePayment->updatePaymentMethodConfirm($subscriptionId, $context);
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return void
     */
    public function cancelPendingSubscriptions(OrderEntity $order, Context $context): void
    {
        # does nothing for now, not necessary
        # because it is not even confirmed yet.
        # but maybe we should add an even in here....
        # let's keep this for now to have it (speaking of the wrapper) fully implemented...
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function pauseSubscription(string $subscriptionId, Context $context): void
    {
        $this->actionPause->pauseSubscription($subscriptionId, $context);
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function resumeSubscription(string $subscriptionId, Context $context): void
    {
        $this->actionResume->resumeSubscription($subscriptionId, $context);
    }

    /**
     * @param string $subscriptionId
     * @param int $skipCount
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function skipSubscription(string $subscriptionId, int $skipCount, Context $context): void
    {
        $this->actionSkip->skipSubscription($subscriptionId, $skipCount, $context);
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function cancelSubscription(string $subscriptionId, Context $context): void
    {
        $this->actionCancel->cancelSubscription($subscriptionId, $context);
    }

    /**
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @throws Exception
     * @return bool
     */
    public function isCancelable(SubscriptionEntity $subscription, Context $context): bool
    {
        return $this->actionCancel->isCancelable($subscription, $context);
    }
}
