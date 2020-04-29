<?php

namespace Kiener\MolliePayments\Subscriber;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Profile;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentMethodSubscriber implements EventSubscriberInterface
{
    /** @var MollieApiClient $apiClient */
    private $apiClient;

    /** @var EntityRepositoryInterface $paymentRepository */
    private $paymentRepository;

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
            PaymentEvents::PAYMENT_METHOD_WRITTEN_EVENT => 'onPaymentMethodChanged'
        ];
    }

    /**
     * Creates a new instance of PaymentMethodSubscriber.
     *
     * @param MollieApiClient $apiClient
     * @param EntityRepositoryInterface $paymentRepository
     */
    public function __construct(
        MollieApiClient $apiClient,
        EntityRepositoryInterface $paymentRepository
    )
    {
        $this->apiClient = $apiClient;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Activates a payment method at Mollie.
     *
     * @param EntityWrittenEvent $args
     * @throws ApiException
     */
    public function onPaymentMethodChanged(EntityWrittenEvent $args)
    {
        foreach ($args->getPayloads() as $payload) {
            $id = null;
            $active = null;

            // Get the payment method ID
            if (isset($payload['id'])) {
                $id = $payload['id'];
            }

            // Get whether the payment method is active
            if (isset($payload['active'])) {
                $active = $payload['active'];
            }

            // Activate payment method at Mollie
            if ($id !== null && (bool) $active === true) {
                $paymentMethod = null;

                try {
                    /** @var PaymentMethodEntity $paymentMethod */
                    $paymentMethod = $this->getPaymentMethodById($id);
                } catch (InconsistentCriteriaIdsException $e) {
                    //
                }

                if ($paymentMethod !== null &&
                    strpos($paymentMethod->getHandlerIdentifier(), 'MolliePayments') !== false) {
                    // Get custom fields of payment method
                    $customFields = $paymentMethod->getCustomFields();

                    // Check if Mollie's payment method name is set
                    if (!isset($customFields['mollie_payment_method_name'])) {
                        continue;
                    }

                    $profile = null;

                    try {
                        /** @var Profile $profile */
                        $profile = $this->apiClient->profiles->get('me');
                    } catch (ApiException $e) {
                        //
                    }

                    if ($profile !== null) {
                        $profile->enableMethod($customFields['mollie_payment_method_name']);
                    }
                }
            }
        }
    }

    /**
     * Get payment method by ID.
     *
     * @param $id
     * @return PaymentMethodEntity
     * @throws InconsistentCriteriaIdsException
     */
    private function getPaymentMethodById($id) : ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('id', $id));

        // Get payment methods
        $paymentMethods = $this->paymentRepository->search($paymentCriteria, Context::createDefaultContext());

        if ($paymentMethods->getTotal() === 0) {
            return null;
        }

        return $paymentMethods->first();
    }
}