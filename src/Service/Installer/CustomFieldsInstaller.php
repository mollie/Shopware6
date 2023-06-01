<?php

declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Installer;

use Kiener\MolliePayments\Repository\CustomFieldSet\CustomFieldSetRepositoryInterface;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldsInstaller
{
    # --------------------------------------------------------------------------------
    private const ID_CUSTOM_FIELDSET = 'f2acb41af0be41638540b31917007fa3';
    private const ID_RELATION_PRDUCTS = 'e01518b929b64fe5bd3ab0f16857dc32';
    # --------------------------------------------------------------------------------
    private const ID_VOUCHER_TYPE = '0488544354354c82a0fdd3bcfcfb4f81';
    private const ID_SUBSCRIPTION_ENABLED = '73f175f0f60849d3ba3512c62c492098';
    private const ID_SUBSCRIPTION_INTERVAL = 'e2ee7ee0fcb44b2c8fb41d91edcc0b16';
    private const ID_SUBSCRIPTION_INTERVAL_UNIT = '5dde0b79c6804c2a93e6137e8699cd0f';
    private const ID_SUBSCRIPTION_REPETITION = '786f49b48bf34a418c5edb5503f31de5';


    /**
     * @var CustomFieldSetRepositoryInterface
     */
    private $repoCustomFields;


    /**
     * @param CustomFieldSetRepositoryInterface $repoCustomFields
     */
    public function __construct(CustomFieldSetRepositoryInterface $repoCustomFields)
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
                'id' => self::ID_CUSTOM_FIELDSET,
                'name' => 'mollie_payments_product',
                'active' => true,
                'translated' => true,
                'config' => [
                    'label' => [
                        'en-GB' => 'Mollie Payments (Product)',
                        'de-DE' => 'Mollie Payments (Produkt)',
                        "nl-NL" => 'Mollie Payments (Product)',
                    ]
                ],
                'relations' => [
                    [
                        'id' => self::ID_RELATION_PRDUCTS,
                        'entityName' => 'product',
                    ]
                ],
                'customFields' => [
                    [
                        'id' => self::ID_VOUCHER_TYPE,
                        'name' => 'mollie_payments_product_voucher_type',
                        'active' => true,
                        'type' => CustomFieldTypes::SELECT,
                        'config' => [
                            'customFieldPosition' => 1,
                            "componentName" => "sw-single-select",
                            "customFieldType" => "select",
                            'label' => [
                                'en-GB' => 'Voucher Type',
                                'de-DE' => 'Gutschein Typ',
                                "nl-NL" => 'Vouchertype',
                            ],
                            'placeholder' => [
                                'en-GB' => 'No voucher type selected',
                                'de-DE' => 'Kein Gutscheintyp ausgewählt',
                                "nl-NL" => 'Geen vouchertype geselecteerd',
                            ],
                            "helpText" => [
                                'en-GB' => 'If this product is eligible for a voucher payment, you have to select a voucher type to enable this payment method in the checkout',
                                'de-DE' => 'Wenn dieses Produkt für eine Gutscheinzahlung berechtigt ist, müssen Sie eine Gutscheinart auswählen, um diese Zahlungsmethode im Shop zu aktivieren',
                                "nl-NL" => 'Als dit product in aanmerking komt voor een voucherbetaling, moet je een vouchertype selecteren om deze betalingsmethode in de winkel in te schakelen',
                            ],
                            "options" => [
                                [
                                    "label" => [
                                        "en-GB" => "Eco",
                                        "de_DE" => "Öko",
                                        "nl-NL" => "Eco",
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
                                        "nl-NL" => "Geschenk",
                                    ],
                                    "value" => VoucherType::TYPE_GIFT
                                ]
                            ],
                        ],
                    ],
                    [
                        'id' => self::ID_SUBSCRIPTION_ENABLED,
                        'name' => 'mollie_payments_product_subscription_enabled',
                        'active' => true,
                        'type' => CustomFieldTypes::SWITCH,
                        'config' => [
                            'customFieldPosition' => 2,
                            "componentName" => "sw-field",
                            "customFieldType" => "switch",
                            'label' => [
                                'en-GB' => 'Subscription Product',
                                'de-DE' => 'Abo-Produkt',
                                "nl-NL" => 'Abonnementsproduct',
                            ],
                        ],
                    ],
                    [
                        'id' => self::ID_SUBSCRIPTION_INTERVAL,
                        'name' => 'mollie_payments_product_subscription_interval',
                        'active' => true,
                        'type' => CustomFieldTypes::INT,
                        'config' => [
                            'customFieldPosition' => 3,
                            "componentName" => "sw-field",
                            "customFieldType" => "number",
                            'label' => [
                                'en-GB' => 'Subscription Interval',
                                'de-DE' => 'Abo-Intervall',
                                "nl-NL" => 'Abonnementsinterval',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter number or leave empty',
                                'de-DE' => 'Nummer eingeben oder leer lassen',
                                "nl-NL" => 'Nummer invoeren of leeg laten',
                            ],
                            "helpText" => [
                                'en-GB' => 'Enter a number in combination with a unit to define a recurring interval',
                                'de-DE' => 'Geben Sie eine Zahl in Kombination mit einer Einheit ein, um ein wiederkehrendes Intervall zu definieren',
                                "nl-NL" => 'Voer een getal in combinatie met een eenheid in om een terugkerend interval te definiëren',
                            ],
                        ],
                    ],
                    [
                        'id' => self::ID_SUBSCRIPTION_INTERVAL_UNIT,
                        'name' => 'mollie_payments_product_subscription_interval_unit',
                        'active' => true,
                        'type' => CustomFieldTypes::SELECT,
                        'config' => [
                            'customFieldPosition' => 4,
                            "componentName" => "sw-single-select",
                            "customFieldType" => "select",
                            'label' => [
                                'en-GB' => 'Subscription Interval (Unit)',
                                'de-DE' => 'Abo-Intervall (Einheit)',
                                "nl-NL" => 'Abonnementsinterval (eenheid)',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Select the interval period type.',
                                'de-DE' => 'Wähle den Typ für den Intervallzeitraum.',
                                "nl-NL" => 'Selecteer het type intervalperiode.',
                            ],
                            "helpText" => [
                                'en-GB' => 'Select the type of the period that you want to use for the recurring payments.',
                                'de-DE' => 'Wählen Sie die Art des Zeitraums aus, den Sie für die wiederkehrenden Zahlungen verwenden möchten.',
                                "nl-NL" => 'Selecteer het type periode dat u wilt gebruiken voor de periodieke betalingen.',
                            ],
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
                        'id' => self::ID_SUBSCRIPTION_REPETITION,
                        'name' => 'mollie_payments_product_subscription_repetition',
                        'active' => true,
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'customFieldPosition' => 5,
                            "componentName" => "sw-field",
                            "customFieldType" => "text",
                            'label' => [
                                'en-GB' => 'Subscription Repetition',
                                'de-DE' => 'Abonnement Wiederholung',
                                "nl-NL" => 'Abonnement herhaling',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter number or leave empty',
                                'de-DE' => 'Nummer eingeben oder leer lassen',
                                "nl-NL" => 'Nummer invoeren of leeg laten',
                            ],
                            "helpText" => [
                                'en-GB' => 'Total number of charges for the subscription to complete. Leave empty for an ongoing subscription.',
                                'de-DE' => 'Gesamtzahl der Gebühren für den Abschluss des Abonnements. Für ein laufendes Abonnement leer lassen.',
                                "nl-NL" => 'Totaal aantal kosten voor het voltooien van het abonnement. Laat leeg voor een doorlopend abonnement.',
                            ],
                        ],
                    ]
                ]
            ]
        ], $context);
    }
}
