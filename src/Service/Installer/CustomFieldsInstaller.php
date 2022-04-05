<?php

declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Installer;

use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\CustomField\CustomFieldTypes;

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
        $this->installProductData($context);
    }

    /**
     * @param Context $context
     * @return void
     */
    private function installProductData(Context $context): void
    {
        $this->repoCustomFields->upsert([
            [
                'id' => 'f2acb41af0be41638540b31917007fa3',
                'name' => 'mollie_payments_product',
                'active' => true,
                'translated' => false,
                'config' => [
                    'label' => [
                        'en-GB' => 'Mollie Payments (Product)',
                        'de-DE' => 'Mollie Payments (Produkt)',
                        "nl-NL" => 'Mollie Payments (Product)',
                    ]
                ],
                'relations' => [
                    [
                        'id' => 'e01518b929b64fe5bd3ab0f16857dc32',
                        'entityName' => 'product',
                    ]
                ],
                'customFields' => [
                    [
                        'id' => '0488544354354c82a0fdd3bcfcfb4f81',
                        'name' => 'mollie_payments_product_voucher_type',
                        'active' => true,
                        'type' => CustomFieldTypes::SELECT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Voucher Type',
                                'de-DE' => 'Gutschein Typ',
                                "nl-NL" => 'Vouchertype',
                            ],
                            'customFieldPosition' => 1,
                            "componentName" => "sw-single-select",
                            "customFieldType" => "select",
                            "options" => [
                                [
                                    "label" => [
                                        "en-GB" => "ECO",
                                        "de_DE" => "Öko",
                                        "nl-NL" => "ECO",
                                    ],
                                    "value" => VoucherType::TYPE_ECO
                                ],
                                [
                                    "label" => [
                                        "en-GB" => "Meal",
                                        "de_DE" => "Mahlzeit",
                                        "nl-NL" => "Meal",
                                    ],
                                    "value" => VoucherType::TYPE_MEAL
                                ],
                                [
                                    "label" => [
                                        "en-GB" => "Gift",
                                        "de_DE" => "Geschenk",
                                        "nl-NL" => "Gift",
                                    ],
                                    "value" => VoucherType::TYPE_GIFT
                                ]
                            ],
                        ],
                    ],
                    [
                        'id' => '73f175f0f60849d3ba3512c62c492098',
                        'name' => 'mollie_payments_product_subscription_enabled',
                        'active' => true,
                        'type' => CustomFieldTypes::SWITCH,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Subscription Product',
                                'de-DE' => 'Abo-Produkt',
                                "nl-NL" => 'Abonnementsproduct',
                            ],
                            'customFieldPosition' => 2,
                            "componentName" => "sw-field",
                            "customFieldType" => "switch",
                        ],
                    ],
                    [
                        'id' => 'e2ee7ee0fcb44b2c8fb41d91edcc0b16',
                        'name' => 'mollie_payments_product_subscription_interval',
                        'active' => true,
                        'type' => CustomFieldTypes::INT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Subscription Interval',
                                'de-DE' => 'Abo-Intervall',
                                "nl-NL" => 'Abonnementsinterval',
                            ],
                            'customFieldPosition' => 3,
                            "componentName" => "sw-field",
                            "customFieldType" => "number",
                        ],
                    ],
                    [
                        'id' => '5dde0b79c6804c2a93e6137e8699cd0f',
                        'name' => 'mollie_payments_product_subscription_interval_unit',
                        'active' => true,
                        'type' => CustomFieldTypes::SELECT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Subscription Interval (Unit)',
                                'de-DE' => 'Abo-Intervall (Einheit))',
                                "nl-NL" => 'Abonnementsinterval (eenheid)',
                            ],
                            'customFieldPosition' => 4,
                            "componentName" => "sw-single-select",
                            "customFieldType" => "select",
                            "options" => [
                                [
                                    "label" => [
                                        "en-GB" => "Days",
                                        'de-DE' => 'Tage',
                                        "nl-NL" => 'Dagen',
                                    ],
                                    "value" => 'days'
                                ],
                                [
                                    "label" => [
                                        "en-GB" => "Weeks",
                                        'de-DE' => 'Wochen',
                                        "nl-NL" => 'Weken',
                                    ],
                                    "value" => 'weeks'
                                ],
                                [
                                    "label" => [
                                        "en-GB" => "Months",
                                        'de-DE' => 'Monate',
                                        "nl-NL" => 'Maanden',
                                    ],
                                    "value" => 'months'
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => '786f49b48bf34a418c5edb5503f31de5',
                        'name' => 'mollie_payments_product_subscription_repetition',
                        'active' => true,
                        'type' => CustomFieldTypes::INT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Subscription Repetition',
                                'de-DE' => 'Abonnement Wiederholung',
                                "nl-NL" => 'Abonnement herhaling',
                            ],
                            "helpText" => [
                                "en-GB" => 'Leave empty to repeat it until canceled',
                                "de-DE" => 'Leer lassen, um das Abo zu wiederholen bis es gekündigt wird',
                                "nl-NL" => 'Laat leeg om het te herhalen totdat het wordt geannuleerd',
                            ],
                            'customFieldPosition' => 5,
                            "componentName" => "sw-field",
                            "customFieldType" => "number",
                        ],
                    ]
                ]
            ]
        ], $context);
    }

}

