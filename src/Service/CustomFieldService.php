<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Exception;
use Kiener\MolliePayments\Repository\CustomFieldSet\CustomFieldSetRepositoryInterface;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldService
{
    public const CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS = 'mollie_payments';


    /**
     * @var CustomFieldSetRepositoryInterface
     */
    private $repoCustomFieldSets;


    /**
     * @param CustomFieldSetRepositoryInterface $customFieldSetRepository
     */
    public function __construct(CustomFieldSetRepositoryInterface $customFieldSetRepository)
    {
        $this->repoCustomFieldSets = $customFieldSetRepository;
    }

    /**
     * @param Context $context
     */
    public function addCustomFields(Context $context): void
    {
        try {
            $fieldSetId = Uuid::randomHex();
            $mollieOrderFieldId = Uuid::randomHex();
            $mollieCustomerFieldId = Uuid::randomHex();
            $iDealIssuerFieldId = Uuid::randomHex();

            $this->repoCustomFieldSets->upsert([[
                'id' => $fieldSetId,
                'name' => 'mollie_payments',
                'config' => [
                    'label' => [
                        'en-GB' => 'Mollie',
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
                                'de-DE' => 'Mollie Kunden ID',
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
                                'de-DE' => 'Mollie Transaktions ID',
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
                                'de-DE' => 'iDeal Bankpräferenz',
                                'nl-NL' => 'iDeal bankvoorkeur'
                            ]
                        ]
                    ]
                ],
                'relations' => [
                    [
                        'id' => Uuid::randomHex(),
                        'entityName' => CustomerDefinition::ENTITY_NAME
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'entityName' => OrderDefinition::ENTITY_NAME
                    ]
                ]
            ]], $context);
        } catch (Exception $e) {
            // @todo Handle Exception
        }
    }
}
