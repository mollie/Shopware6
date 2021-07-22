<?php

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\MollieApi\Customer as CustomerApi;
use Mollie\Api\Endpoints\CustomerEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use PHPUnit\Framework\TestCase;

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
     * @dataProvider getMollieCustomerByIdTestData
     */
    public function testGetMollieCustomerById(
        string $mollieCustomerId,
        ?string $expectedReturnClass = null,
        ?string $expectedException = null
    )
    {
        if(!is_null($expectedException)) {
            $this->expectException($expectedException);
        }

        $result = $this->customerApiService->getMollieCustomerById($mollieCustomerId, '');

        if(!is_null($expectedReturnClass)) {
            $this->assertInstanceOf($expectedReturnClass, $result);
        }
    }

    public function getMollieCustomerByIdTestData(): array
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
}
