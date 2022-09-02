<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomFieldService
{
    public const CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS = 'mollie_payments';


    /**
     * @var EntityRepositoryInterface
     */
    private $customFieldSetRepository;


    /**
     * @param EntityRepositoryInterface $customFieldSetRepository
     */
    public function __construct(EntityRepositoryInterface $customFieldSetRepository)
    {
        $this->customFieldSetRepository = $customFieldSetRepository;
    }

    /**
     * @param Context $context
     */
    public function addCustomFields(Context $context): void
    {
        try {
            $mollieOrderFieldId = Uuid::randomHex();
            $mollieCustomerFieldId = Uuid::randomHex();
            $iDealIssuerFieldId = Uuid::randomHex();

            $this->customFieldSetRepository->upsert([[
                'id' => Uuid::randomHex(),
                'name' => 'mollie_payments',
                'config' => [
                    'label' => [
                        'en-GB' => 'Mollie'
                    ]
                ],
                'customFields' => [
                    [
                        'id' => $mollieCustomerFieldId,
                        'name' => 'customer_id',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'componentName' => 'sw-field',
                            'customFieldType' => CustomFieldTypes::TEXT,
                            'customFieldPosition' => 1,
                            'label' => [
                                'en-GB' => 'Mollie customer ID',
                                'nl-NL' => 'Mollie customer ID'
                            ]
                        ]
                    ],
                    [
                        'id' => $mollieOrderFieldId,
                        'name' => 'order_id',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'componentName' => 'sw-field',
                            'customFieldType' => CustomFieldTypes::TEXT,
                            'customFieldPosition' => 1,
                            'label' => [
                                'en-GB' => 'Mollie transaction ID',
                                'nl-NL' => 'Mollie transactienummer'
                            ]
                        ]
                    ],
                    [
                        'id' => $iDealIssuerFieldId,
                        'name' => 'preferred_ideal_issuer',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'componentName' => 'sw-field',
                            'customFieldType' => CustomFieldTypes::TEXT,
                            'customFieldPosition' => 1,
                            'label' => [
                                'en-GB' => 'Preferred iDeal issuer',
                                'nl-NL' => 'iDeal bankvoorkeur'
                            ]
                        ]
                    ]
                ],
                'relations' => [
                    [
                        'id' => $mollieCustomerFieldId,
                        'entityName' => CustomerDefinition::ENTITY_NAME
                    ],
                    [
                        'id' => $mollieOrderFieldId,
                        'entityName' => OrderDefinition::ENTITY_NAME
                    ],
                    [
                        'id' => $iDealIssuerFieldId,
                        'entityName' => CustomerDefinition::ENTITY_NAME
                    ]
                ]
            ]], $context);
        } catch (Exception $e) {
            // @todo Handle Exception
        }
    }
}
