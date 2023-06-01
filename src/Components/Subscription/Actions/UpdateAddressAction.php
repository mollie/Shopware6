<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class UpdateAddressAction extends BaseAction
{
    /**
     * @var OrderService
     */
    private $orderService;


    /**
     * @param SettingsService $pluginSettings
     * @param SubscriptionRepository $repoSubscriptions
     * @param SubscriptionBuilder $subscriptionBuilder
     * @param MollieDataBuilder $mollieRequestBuilder
     * @param CustomerService $customers
     * @param MollieGatewayInterface $gwMollie
     * @param CancellationValidator $cancellationValidator
     * @param FlowBuilderFactory $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param SubscriptionHistoryHandler $subscriptionHistory
     * @param LoggerInterface $logger
     * @param OrderService $orderService
     * @throws Exception
     */
    public function __construct(SettingsService $pluginSettings, SubscriptionRepository $repoSubscriptions, SubscriptionBuilder $subscriptionBuilder, MollieDataBuilder $mollieRequestBuilder, CustomerService $customers, MollieGatewayInterface $gwMollie, CancellationValidator $cancellationValidator, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionHistoryHandler $subscriptionHistory, LoggerInterface $logger, OrderService $orderService)
    {
        parent::__construct(
            $pluginSettings,
            $repoSubscriptions,
            $subscriptionBuilder,
            $mollieRequestBuilder,
            $customers,
            $gwMollie,
            $cancellationValidator,
            $flowBuilderFactory,
            $flowBuilderEventFactory,
            $subscriptionHistory,
            $logger
        );

        $this->orderService = $orderService;
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
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription Billing Address cannot be updated. Subscriptions are disabled for this Sales Channel');
        }

        if (!$settings->isSubscriptionsAllowAddressEditing()) {
            throw new Exception('Editing of the billing address on running subscriptions is not allowed in the plugin configuration');
        }

        $address = $subscription->getBillingAddress();

        if (!$address instanceof SubscriptionAddressEntity) {
            $address = $this->createNewAddress($subscription, $context);
        }

        $address->setSalutationId($salutationId);
        $address->setTitle($title);
        $address->setFirstName($firstname);
        $address->setLastName($lastname);

        $address->setCompany($company);
        $address->setDepartment($department);

        $address->setAdditionalAddressLine1($additional1);
        $address->setAdditionalAddressLine2($additional2);

        $address->setPhoneNumber($phoneNumber);

        $address->setStreet($street);
        $address->setZipcode($zipcode);
        $address->setCity($city);
        $address->setCountryStateId($countryStateId);

        $this->getRepository()->assignBillingAddress($subscriptionId, $address, $context);

        # also add a history entry for this subscription
        $this->getStatusHistory()->markBillingUpdated($subscription, $context);
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
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription Shipping Address cannot be updated. Subscriptions are disabled for this Sales Channel');
        }

        if (!$settings->isSubscriptionsAllowAddressEditing()) {
            throw new Exception('Editing of the shipping address on running subscriptions is not allowed in the plugin configuration');
        }

        $address = $subscription->getShippingAddress();

        if (!$address instanceof SubscriptionAddressEntity) {
            $address = $this->createNewAddress($subscription, $context);
        }

        $address->setSalutationId($salutationId);
        $address->setTitle($title);
        $address->setFirstName($firstname);
        $address->setLastName($lastname);

        $address->setCompany($company);
        $address->setDepartment($department);

        $address->setPhoneNumber($phoneNumber);

        $address->setAdditionalAddressLine1($additional1);
        $address->setAdditionalAddressLine2($additional2);

        $address->setStreet($street);
        $address->setZipcode($zipcode);
        $address->setCity($city);
        $address->setCountryStateId($countryStateId);

        $this->getRepository()->assignShippingAddress($subscriptionId, $address, $context);

        # also add a history entry for this subscription
        $this->getStatusHistory()->markShipping($subscription, $context);
    }


    /**
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @throws Exception
     * @return SubscriptionAddressEntity
     */
    private function createNewAddress(SubscriptionEntity $subscription, Context $context): SubscriptionAddressEntity
    {
        $initialOrder = $this->orderService->getOrder($subscription->getOrderId(), $context);

        if (!$initialOrder instanceof OrderEntity) {
            throw new Exception('No initial order found for subscription: ' . $subscription->getId());
        }

        $initialAddress = $initialOrder->getBillingAddress();

        if (!$initialAddress instanceof OrderAddressEntity) {
            throw new Exception('No address found for initial order');
        }

        $address = new SubscriptionAddressEntity();

        $address->setId(Uuid::randomHex());
        $address->setCountryId($initialAddress->getCountryId());

        return $address;
    }
}
