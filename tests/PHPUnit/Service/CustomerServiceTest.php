<?php

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGateway;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Customer;
use Kiener\MolliePayments\Service\SettingsService;
use MolliePayments\Tests\Fakes\FakeEntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerServiceTest extends TestCase
{
    /** @var CustomerService */
    private $customerService;

    # Mock objects
    /** @var CompatibilityGateway */
    private $compatibilityGateway;

    /** @var EntityRepositoryInterface */
    private $customerRepository;

    /** @var SalesChannelContextPersister */
    private $salesChannelContextPersister;

    /** @var SettingsService */
    private $settingsService;

    public function setUp(): void
    {
        $this->compatibilityGateway = $this->createMock(CompatibilityGateway::class);
        $this->customerRepository = new FakeEntityRepository(new CustomerDefinition());
        $this->salesChannelContextPersister = $this->createMock(SalesChannelContextPersister::class);
        $this->settingsService = $this->createMock(SettingsService::class);

        $this->customerService = new CustomerService(
            $this->createMock(EntityRepositoryInterface::class),
            $this->customerRepository,
            $this->createMock(Customer::class),
            $this->createMock(EventDispatcherInterface::class),
            new NullLogger(),
            $this->salesChannelContextPersister,
            $this->createMock(EntityRepositoryInterface::class),
            $this->settingsService,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->compatibilityGateway
        );

    }

    /**
     * @return void
     */
    public function testCustomerLogin()
    {
        $this->compatibilityGateway->method('getSalesChannelID')->willReturn('foo');

        $customer = $this->createConfiguredMock(CustomerEntity::class, [
            'getId'    => 'bar',
            'getEmail' => 'foo@bar.baz',
        ]);

        $context = $this->createConfiguredMock(SalesChannelContext::class, [
            'getToken' => 'baz',
        ]);

        $this->salesChannelContextPersister->method('replace')->willReturn('token');

        $this->compatibilityGateway->expects($this->once())->method('persistSalesChannelContext')->with('token', 'foo', 'bar');

        $this->customerService->customerLogin($customer, $context);
    }

    /**
     * @param string $customerId
     * @param string $mollieCustomerId
     * @param string $profileId
     * @param bool   $testMode
     * @dataProvider setMollieCustomerIdTestData
     */
    public function testSetMollieCustomerId(
        string $customerId,
        string $mollieCustomerId,
        string $profileId,
        bool   $testMode,
        array  $existingCustomFields,
        array  $expectedCustomFields
    )
    {
        $customer = $this->createConfiguredMock(CustomerEntity::class, [
            'getCustomFields' => $existingCustomFields,
        ]);

        $search = $this->createConfiguredMock(EntitySearchResult::class, [
            'first' => $customer,
        ]);

        $this->customerRepository->entitySearchResults = [$search];

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $this->customerRepository->entityWrittenContainerEvents = [$event];

        $this->customerService->setMollieCustomerId(
            $customerId,
            $mollieCustomerId,
            $profileId,
            $testMode,
            $this->createMock(Context::class)
        );

        $savedData = array_pop($this->customerRepository->data);
        $savedCustomerData = $savedData[0];

        $this->assertSame($customerId, $savedCustomerData['id']);
        $this->assertSame($expectedCustomFields, $savedCustomerData['customFields']);
    }

    /**
     * Please be aware that the expected customFields is only what is expected to be passed to
     * customerRepository::update, and this does mean it is consolidated with the existing customFields.
     * Normally, this is something the EntityRepository takes care of.
     */
    public function setMollieCustomerIdTestData()
    {
        return [
            'New Mollie customer, live' => [
                'foo', 'cst_123', 'pfl_123', false,
                [], // existing customfields
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_123' => [
                                'live' => 'cst_123',
                                'test' => '',
                            ]
                        ]
                    ]
                ]
            ],
            'New Mollie customer, test' => [
                'bar', 'cst_321', 'pfl_321', true,
                [], // existing customfields
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_321' => [
                                'live' => '',
                                'test' => 'cst_321'
                            ]
                        ]
                    ]
                ]
            ],
            'Existing Mollie customer, live' => [
                'baz', 'cst_456', 'pfl_456', false,
                [   // existing customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_456' => [
                                'live' => 'cst_456',
                                'test' => '',
                            ]
                        ]
                    ]
                ],
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_456' => [
                                'live' => 'cst_456',
                                'test' => '',
                            ]
                        ]
                    ]
                ]
            ],
            'Existing Mollie customer, test' => [
                'bax', 'cst_654', 'pfl_654', true,
                [   // existing customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_654' => [
                                'live' => '',
                                'test' => 'cst_654'
                            ]
                        ]
                    ]
                ],
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_654' => [
                                'live' => '',
                                'test' => 'cst_654'
                            ]
                        ]
                    ]
                ]
            ],
            'Existing legacy Mollie customer, matches with live profile' => [
                'fizz', 'cst_789', 'pfl_789', false,
                [   // existing customfields
                    'customer_id' => 'cst_789',
                ],
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_789' => [
                                'live' => 'cst_789',
                                'test' => '',
                            ]
                        ]
                    ],
                    'customer_id' => null,
                ]
            ],
            'Existing legacy Mollie customer, does not match with live profile' => [
                'buzz', 'cst_789', 'pfl_789', false,
                [   // existing customfields
                    'customer_id' => 'cst_987',
                ],
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_789' => [
                                'live' => 'cst_789',
                                'test' => '',
                            ]
                        ]
                    ],
                    'customer_id' => 'cst_987',
                ]
            ],
        ];
    }
}
