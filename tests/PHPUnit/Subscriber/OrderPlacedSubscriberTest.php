<?php

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Customer;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Subscriber\OrderPlacedSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;

class OrderPlacedSubscriberTest extends TestCase
{
    /** @var CustomerService */
    private $customerService;

    /** @var Customer */
    private $customerApiService;

    /** @var MollieSettingStruct */
    private $settings;

    /** @var OrderPlacedSubscriber */
    private $subscriber;


    /** @var CustomerEntity */
    private $customer;

    /** @var OrderEntity */
    private $order;

    /** @var PaymentMethodEntity */
    private $paymentMethod;


    public function setUp(): void
    {
        $this->customerService = $this->createMock(CustomerService::class);
        $this->customerApiService = $this->createMock(Customer::class);

        $this->settings = new MollieSettingStruct();

        $settingsService = $this->createConfiguredMock(SettingsService::class, [
            'getSettings' => $this->settings
        ]);

        $this->subscriber = new OrderPlacedSubscriber(
            $this->customerService,
            $this->customerApiService,
            new MolliePaymentExtractor(),
            $settingsService,
            new NullLogger()
        );

        $this->customer = $this->createMock(CustomerEntity::class);
        $orderCustomer = $this->createConfiguredMock(OrderCustomerEntity::class, [
            'getCustomer' => $this->customer
        ]);

        $this->paymentMethod = $this->createMock(PaymentMethodEntity::class);

        $transaction = $this->createConfiguredMock(OrderTransactionEntity::class, [
            'getUniqueIdentifier' => 'bar',
            'getPaymentMethod' => $this->paymentMethod
        ]);
        $transactionCollection = new OrderTransactionCollection([$transaction]);

        $this->order = $this->createConfiguredMock(OrderEntity::class, [
            'getOrderCustomer' => $orderCustomer,
            'getTransactions' => $transactionCollection
        ]);
    }

    /**
     * @param string $customerId
     * @param bool $customerIsGuest
     * @param string $paymentMethodClass
     * @param bool $createCustomersAtMollie
     * @param bool $shouldCreateMollieCustomer
     * @dataProvider createMollieCustomerIsCalledTestData
     */
    public function testThatCreateMollieCustomerIsCalled(
        string $customerId,
        bool   $customerIsGuest,
        string $salesChannelId,
        string $paymentMethodClass,
        bool   $createCustomersAtMollie,
        bool   $shouldCreateMollieCustomer
    )
    {
        $this->customer->method('getId')->willReturn($customerId);
        $this->customer->method('getGuest')->willReturn($customerIsGuest);
        $this->paymentMethod->method('getHandlerIdentifier')->willReturn($paymentMethodClass);
        $this->settings->setCreateCustomersAtMollie($createCustomersAtMollie);

        $context = $this->createMock(Context::class);

        $event = new CheckoutOrderPlacedEvent(
            $context,
            $this->order,
            $salesChannelId
        );

        if ($shouldCreateMollieCustomer) {
            $this->customerService
                ->expects($this->once())
                ->method('createMollieCustomer')
                ->with($customerId, $salesChannelId, $context);
        } else {
            $this->customerService
                ->expects($this->never())
                ->method('createMollieCustomer');
        }

        $this->subscriber->createCustomerAtMollie($event);
    }

    public function createMollieCustomerIsCalledTestData()
    {
        return [
            'customer is not a guest, iDeal, create customers on => create customer' => [
                'foo', false, 'bar', iDealPayment::class, true, true
            ],
            'customer is guest, iDeal, create customers on => do not create customer' => [
                'foo', true, 'bar', iDealPayment::class, true, false
            ],
            'customer is not a guest, Cash on Delivery, create customers on => do not create customer' => [
                'foo', false, 'bar', CashPayment::class, true, false
            ],
            'customer is not a guest, iDeal, create customers off => do not create customer' => [
                'foo', false, 'bar', iDealPayment::class, false, false
            ]
        ];
    }
}
