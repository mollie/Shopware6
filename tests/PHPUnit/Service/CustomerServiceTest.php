<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Customer;
use Kiener\MolliePayments\Service\SettingsService;
use MolliePayments\Tests\Fakes\FakeTranslator;
use MolliePayments\Tests\Fakes\Repositories\FakeCustomerRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomerServiceTest extends TestCase
{
    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var CustomerService */
    private $customerService;

    /** @var SettingsService */
    private $settingsService;

    public function setUp(): void
    {
        $this->customerRepository = new FakeCustomerRepository(new CustomerDefinition());
        $fakeTranslator = new FakeTranslator();
        $this->settingsService = $this->createMock(SettingsService::class);

        $this->customerService = new CustomerService(
            $this->createMock(EntityRepository::class),
            $this->customerRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(Customer::class),
            $this->createMock(EventDispatcherInterface::class),
            new NullLogger(),
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(EntityRepository::class),
            $this->settingsService,
            'does.not.matter.here',
            $this->createMock(ConfigService::class),
            $this->createMock(ContainerInterface::class),
            $this->createMock(RequestStack::class),
            $fakeTranslator
        );
    }

    /**
     * This test makes sure that, if we have invalid mollie_payments custom fields, that the struct will be empty
     *
     * @throws \Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException
     */
    public function testCustomerCustomFieldsAreInvalid(): void
    {
        $customer = $this->createConfiguredMock(CustomerEntity::class, [
            'getCustomFields' => ['mollie_payments' => 'foo'],
        ]);

        $search = $this->createConfiguredMock(EntitySearchResult::class, [
            'first' => $customer,
        ]);

        $this->customerRepository->entitySearchResults = [$search];

        $customerStruct = $this->customerService->getCustomerStruct('fakeId', $this->createMock(Context::class));

        $actual = json_encode($customerStruct);
        $expected = '{"extensions":[]}';

        $this->assertEquals($actual, $expected);
    }

    /**
     * @dataProvider setMollieCustomerIdTestData
     */
    public function testSetMollieCustomerId(
        string $customerId,
        string $mollieCustomerId,
        string $profileId,
        bool $testMode,
        array $existingCustomFields,
        array $expectedCustomFields
    ) {
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
                            ],
                        ],
                    ],
                ],
            ],
            'New Mollie customer, test' => [
                'bar', 'cst_321', 'pfl_321', true,
                [], // existing customfields
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_321' => [
                                'live' => '',
                                'test' => 'cst_321',
                            ],
                        ],
                    ],
                ],
            ],
            'Existing Mollie customer, live' => [
                'baz', 'cst_456', 'pfl_456', false,
                [   // existing customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_456' => [
                                'live' => 'cst_456',
                                'test' => '',
                            ],
                        ],
                    ],
                ],
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_456' => [
                                'live' => 'cst_456',
                                'test' => '',
                            ],
                        ],
                    ],
                ],
            ],
            'Existing Mollie customer, test' => [
                'bax', 'cst_654', 'pfl_654', true,
                [   // existing customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_654' => [
                                'live' => '',
                                'test' => 'cst_654',
                            ],
                        ],
                    ],
                ],
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_654' => [
                                'live' => '',
                                'test' => 'cst_654',
                            ],
                        ],
                    ],
                ],
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
                            ],
                        ],
                    ],
                    'customer_id' => null,
                ],
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
                            ],
                        ],
                    ],
                    'customer_id' => 'cst_987',
                ],
            ],
            'Broken mollie_payments custom Fields by external plugins' => [
                'bar', 'cst_321', 'pfl_321', true,
                ['mollie_payments' => 'foo'], // existing customfields
                [   // expected customfields
                    'mollie_payments' => [
                        'customer_ids' => [
                            'pfl_321' => [
                                'live' => '',
                                'test' => 'cst_321',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
