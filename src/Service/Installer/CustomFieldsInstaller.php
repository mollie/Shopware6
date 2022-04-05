<?php

declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Installer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Framework\Context;

class CustomFieldsInstaller
{

    /**
     * @var EntityRepositoryInterface
     */
    private $repoCustomFields;

    /**
     * @param EntityRepositoryInterface $repoCustomFields
     */
    public function __construct(EntityRepositoryInterface $repoCustomFields)
    {
        $this->repoCustomFields = $repoCustomFields;
    }


    /**
     * @param Context $context
     */
    public function install(Context $context): void
    {
        $this->repoCustomFields->upsert([
            [
                'id' => 'f2acb41af0be41638540b31917007fa3',
                'name' => 'mollie_payments',
                'active' => true,
                'translated' => false,
                'config' => [
                    'label' => [
                        'en-GB' => 'Mollie Payments',
                        'de-DE' => 'Mollie Payments'
                    ]
                ],
                'relations' => [
                    [
                        # TODO duplicate key exception kommt noch
                        'id' => 'e01518b929b64fe5bd3ab0f16857dc32',
                        'entityName' => 'product',
                    ]
                ],
                'customFields' => [
                    [
                        'id' => '0488544354354c82a0fdd3bcfcfb4f81',
                        'name' => 'mollie_payments.voucher_type',
                        'active' => true,
                        'type' => CustomFieldTypes::INT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Voucher Type',
                                'de-DE' => 'Gutschein Typ',
                            ],
                            'customFieldPosition' => 1,
                        ],
                    ],
                    [
                        'id' => '73f175f0f60849d3ba3512c62c492098',
                        'name' => 'mollie_payments.subscription_product',
                        'active' => true,
                        'type' => CustomFieldTypes::BOOL,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Subscription Product',
                                'de-DE' => 'Abo Produkt',
                            ],
                            'customFieldPosition' => 2,
                        ],
                    ]
                ]
            ]
        ], $context);
    }

}