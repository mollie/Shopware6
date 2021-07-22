<?php

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\MollieApi\Customer as CustomerApi;
use Mollie\Api\Endpoints\CustomerEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class CustomerTest extends TestCase
{
    /**
     * @var CustomerApi
     */
    private $customerApiService;

    public function setUp(): void
    {
        $customerEndpoint = $this->createMock(CustomerEndpoint::class);
        $customerEndpoint->method('get')->will(
            $this->returnCallback(function ($arg) {
                if ($arg == 'bar') {
                    throw new ApiException();
                } else {
                    return $this->createMock(Customer::class);
                }
            })
        );
        $customerEndpoint->method('create')->will(
            $this->returnCallback(function ($arg) {
                if ($arg['email'] == 'existing.email@ddress.com') {
                    throw new ApiException();
                } else {
                    return $this->createMock(Customer::class);
                }
            })
        );

        $apiClient = $this->createMock(MollieApiClient::class);
        $apiClient->customers = $customerEndpoint;

        $clientFactory = $this->createMock(MollieApiFactory::class);
        $clientFactory->method('getClient')->willReturn($apiClient);

        $this->customerApiService = new CustomerApi($clientFactory);
    }

    /**
     * @param string $mollieCustomerId
     * @param string|null $expectedReturnClass
     * @param string|null $expectedException
     * @throws CouldNotFetchMollieCustomerException
     * @dataProvider mollieCustomerByIdTestData
     */
    public function testGetMollieCustomerById(
        string $mollieCustomerId,
        ?string $expectedReturnClass = null,
        ?string $expectedException = null
    )
    {
        if (!is_null($expectedException)) {
            $this->expectException($expectedException);
        }

        $result = $this->customerApiService->getMollieCustomerById($mollieCustomerId, '');

        if (!is_null($expectedReturnClass)) {
            $this->assertInstanceOf($expectedReturnClass, $result);
        }
    }

    /**
     * @param string $email
     * @param string|null $expectedReturnClass
     * @param string|null $expectedException
     * @throws CouldNotCreateMollieCustomerException
     * @dataProvider createCustomerAtMollieTestData
     */
    public function testCreateCustomersAtMollie(
        string $email,
        ?string $expectedReturnClass = null,
        ?string $expectedException = null
    )
    {
        if (!is_null($expectedException)) {
            $this->expectException($expectedException);
        }

        $customerMock = $this->createConfiguredMock(CustomerEntity::class, [
            'getFirstName' => 'Foo',
            'getLastName' => 'Bar',
            'getEmail' => $email,
            'getCustomerNumber' => '12345',
            'getId' => 'fizz',
            'getSalesChannelId' => 'buzz',
        ]);

        $result = $this->customerApiService->createCustomerAtMollie($customerMock);

        if (!is_null($expectedReturnClass)) {
            $this->assertInstanceOf($expectedReturnClass, $result);
        }
    }

    public function mollieCustomerByIdTestData(): array
    {
        return [
            'Customer exists in Mollie' => [
                'foo',
                Customer::class,
                null
            ],
            'Customer does not exist in Mollie' => [
                'bar',
                null,
                CouldNotFetchMollieCustomerException::class
            ]
        ];
    }

    public function createCustomerAtMollieTestData(): array
    {
        return [
            'Customer does not exist yet' => [
                'new.email@ddress.com',
                Customer::class,
                null
            ],
            'Customer already exists in Mollie' => [
                'existing.email@ddress.com',
                null,
                CouldNotCreateMollieCustomerException::class
            ]
        ];
    }
}
