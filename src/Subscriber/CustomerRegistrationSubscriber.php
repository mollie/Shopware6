<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use RuntimeException;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerRegistrationSubscriber implements EventSubscriberInterface
{

    /** @var MollieApiClient */
    private $apiClient;

    /** @var CustomerService */
    private $customerService;

    /** @var SettingsService */
    private $settingsService;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'customer.written' => 'onCustomerRegistration',
        ];
    }

    /**
     * Creates a new instance of PaymentMethodSubscriber.
     *
     * @param MollieApiClient $apiClient
     */
    public function __construct(
        MollieApiClient $apiClient,
        CustomerService $customerService,
        SettingsService $settingsService

    ) {
        $this->apiClient = $apiClient;
        $this->customerService = $customerService;
        $this->settingsService = $settingsService;
    }

    /**
     * Refunds the transaction at Mollie if the payment state is refunded.
     *
     * @param CustomerRegisterEvent $customerRegisterEvent
     */
    public function onCustomerRegistration(EntityWrittenEvent $entityWrittenEvent): void
    {
        $context = $entityWrittenEvent->getContext();

        if ($context === null) {
            return;
        }

        $source = $context->getSource();

        if ($source === null && !method_exists($source, 'getSalesChannelId')) {
            return;
        }

        $settings = $this->settingsService->getSettings($source->getSalesChannelId());

        if ($settings->isTestMode() && $settings->createNoCustomersAtMollie()) {
            return;
        }

        foreach ($entityWrittenEvent->getPayloads() as $payload) {
            $id = null;
            $name = null;
            $email = null;
            $guest = null;

            if (isset($payload['id'])) {
                $id = $payload['id'];
            }

            if (isset($payload['firstName'], $payload['lastName'])) {
                $name = \sprintf('%s %s', $payload['firstName'], $payload['lastName']);
            }

            if (isset($payload['email'])) {
                $email = $payload['email'];
            }

            if (isset($payload['guest'])) {
                $guest = $payload['guest'];
            }

            if ($name === null && $email === null && $id === null && !$guest) {
                return;
            }

            try {
                $mollieCustomer = $this->apiClient->customers->create(
                    [
                        'name'  => $name,
                        'email' => $email,
                    ]
                );

                $customer = $this->customerService->getCustomer($id, $entityWrittenEvent->getContext());

                if (
                    (
                        \array_key_exists('customFields', $payload) &&
                        \array_key_exists('customer_id', $payload['customFields'])
                    ) ||
                    $customer === null
                ) {
                    return;
                }

                $customer->setCustomFields(
                    [
                        'customer_id' => $mollieCustomer->id
                    ]
                );

                $this->customerService->saveCustomerCustomFields(
                    $customer,
                    $customer->getCustomFields(),
                    $entityWrittenEvent->getContext()
                );
            } catch (ApiException $e) {
            }
        }
    }
}
