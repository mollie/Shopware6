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
        $customerEndpoint->method('get')->willReturnCallback(
            function ($arg) {
                if ($arg == 'bar') {
                    throw new ApiException();
                } else {
                    return $this->createMock(Customer::class);
                }
            }
        );
        $customerEndpoint->method('create')->willReturnCallback(
            function ($arg) {
                if ($arg['email'] == 'existing.email@ddress.com') {
                    throw new ApiException();
                }

                return $this->createMock(Customer::class);
            }
        );

        $apiClient = $this->createMock(MollieApiClient::class);
        $apiClient->customers = $customerEndpoint;

        $clientFactory = $this->createMock(MollieApiFactory::class);
        $clientFactory->method('getClient')->willReturn($apiClient);

        $this->customerApiService = new CustomerApi($clientFactory);
    }

    public function testThatMollieCustomerExists()
    {
        $actualInstance = $this->customerApiService->getMollieCustomerById('foo', '');
        $this->assertInstanceOf(Customer::class, $actualInstance);
    }

    public function testThatExceptionIsThrownIfCustomerDoesNotExist()
    {
        $this->expectException(CouldNotFetchMollieCustomerException::class);
        $this->customerApiService->getMollieCustomerById('bar', '');
    }

    public function testCreatingNewCustomerAtMollie()
    {
        $customerMock = $this->createConfiguredMock(CustomerEntity::class, [
            'getFirstName' => 'Foo',
            'getLastName' => 'Bar',
            'getEmail' => 'new.email@ddress.com',
            'getCustomerNumber' => '12345',
            'getId' => 'fizz',
            'getSalesChannelId' => 'buzz',
        ]);

        $actualInstance = $this->customerApiService->createCustomerAtMollie($customerMock, 'buzz');
        $this->assertInstanceOf(Customer::class, $actualInstance);
    }

    public function testCreatingNewCustomerAtMollieWithExistingEmailAddress()
    {
        $this->expectException(CouldNotCreateMollieCustomerException::class);

        $customerMock = $this->createConfiguredMock(CustomerEntity::class, [
            'getFirstName' => 'Foo',
            'getLastName' => 'Bar',
            'getEmail' => 'existing.email@ddress.com',
            'getCustomerNumber' => '12345',
            'getId' => 'fizz',
            'getSalesChannelId' => 'buzz',
        ]);

        $this->customerApiService->createCustomerAtMollie($customerMock, 'buzz');
    }

    /**
     * @param null|string $mollieCustomerId
     * @param bool $expectedValue
     * @dataProvider isLegacyCustomerValidTestData
     */
    public function testIsLegacyCustomerValid(
        ?string $mollieCustomerId,
        bool $expectedValue
    ) {
        $actualValue = $this->customerApiService->isLegacyCustomerValid($mollieCustomerId, '');

        $this->assertIsBool($actualValue);
        $this->assertSame($expectedValue, $actualValue);
    }

    public function isLegacyCustomerValidTestData(): array
    {
        return [
            'Customer exists in Mollie' => ['foo', true],
            'Customer does not exist in Mollie' => ['bar', false],
            'Customer Id is null' => [null, false],
        ];
    }
}
