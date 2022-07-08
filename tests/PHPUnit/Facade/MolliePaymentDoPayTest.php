<?php

namespace MolliePayments\Tests\Facade;

use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\Order\UpdateOrderLineItems;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use MolliePayments\Tests\Fakes\FakeSubscriptionManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class MolliePaymentDoPayTest extends TestCase
{
    /** @var CustomerService */
    private $customerService;

    /** @var MollieSettingStruct */
    private $settings;

    /** @var MolliePaymentDoPay */
    private $payFacade;

    /** @var CustomerEntity */
    private $customer;

    /** @var OrderEntity */
    private $order;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    public function setUp(): void
    {
        $this->settings = new MollieSettingStruct();

        $settingsService = $this->createConfiguredMock(SettingsService::class, [
            'getSettings' => $this->settings,
        ]);

        $this->customer = $this->createConfiguredMock(CustomerEntity::class, [
            'getId' => 'foo',
        ]);
        $this->customerService = $this->createConfiguredMock(CustomerService::class, [
            'getCustomer' => $this->customer
        ]);

        $orderCustomer = $this->createConfiguredMock(OrderCustomerEntity::class, [
            'getCustomerId' => 'foo',
        ]);
        $this->order = $this->createConfiguredMock(OrderEntity::class, [
            'getOrderCustomer' => $orderCustomer,
        ]);

        $orderDataExtractor = new OrderDataExtractor(
            new NullLogger(),
            $this->customerService
        );

        $salesChannel = $this->createConfiguredMock(SalesChannelEntity::class, [
            'getId' => 'bar',
        ]);

        $context = $this->createMock(Context::class);

        $this->salesChannelContext = $this->createConfiguredMock(SalesChannelContext::class, [
            'getSalesChannel' => $salesChannel,
            'getContext' => $context
        ]);

        $this->payFacade = new MolliePaymentDoPay(
            $orderDataExtractor,
            $this->createMock(MollieOrderBuilder::class),
            $this->createMock(OrderService::class),
            $this->createMock(Order::class),
            $this->customerService,
            $settingsService,
            $this->createMock(UpdateOrderCustomFields::class),
            $this->createMock(UpdateOrderLineItems::class),
            new FakeSubscriptionManager(),
            new NullLogger()
        );
    }

    /**
     * @param bool $customerIsGuest
     * @param bool $createCustomersAtMollie
     * @param bool $shouldCreateMollieCustomer
     * @throws CouldNotCreateMollieCustomerException
     * @throws CustomerCouldNotBeFoundException
     * @dataProvider createMollieCustomerIsCalledTestData
     */
    public function testIfCreateMollieCustomerIsCalled(
        bool $customerIsGuest,
        bool $createCustomersAtMollie,
        bool $shouldCreateMollieCustomer
    )
    {
        $this->customer->method('getGuest')->willReturn($customerIsGuest);
        $this->settings->setCreateCustomersAtMollie($createCustomersAtMollie);

        if ($shouldCreateMollieCustomer) {
            $this->customerService
                ->expects($this->once())
                ->method('createMollieCustomer')
                ->with('foo', 'bar', $this->salesChannelContext->getContext());
        } else {
            $this->customerService
                ->expects($this->never())
                ->method('createMollieCustomer');
        }

        $this->payFacade->createCustomerAtMollie($this->order, $this->salesChannelContext);
    }

    public function createMollieCustomerIsCalledTestData()
    {
        return [
            'customer is not a guest, create customers on => create customer' => [
                false, true, true
            ],
            'customer is guest, create customers on => do not create customer' => [
                true, true, false
            ],
            'customer is not a guest, create customers off => do not create customer' => [
                false, false, false
            ],
            'customer is guest, create customers off => do not create customer' => [
                true, false, false
            ]
        ];
    }
}
