<?php


namespace MolliePayments\Fixtures\Customer;


use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use Kiener\MolliePayments\Repository\Customer\CustomerRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


class CustomerFixture extends Fixture
{

    private const CUSTOMER_ID_NL = '0d8eefdd6d32456385580e2ff42431b9';
    private const ADDRESS_ID_NL = 'e27dc2b4e85f4a0f9a912a09f07701b0';

    private const CUSTOMER_ID_DE = '0d8defdd6d32456385580e2ff42431b9';
    private const ADDRESS_ID_DE = 'e27ddeb4e85f4a0f9a912a09f07701b0';


    /**
     * @var FixtureHelper
     */
    private $helper;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;


    /**
     * @param FixtureHelper $helper
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(FixtureHelper $helper, CustomerRepositoryInterface $customerRepository)
    {
        $this->helper = $helper;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @return string[]
     */
    public function groups(): array
    {
        return [
            'mollie',
            'mollie-demodata',
        ];
    }

    /**
     * @param FixtureBag $bag
     * @return void
     */
    public function load(FixtureBag $bag): void
    {
        $salesChannel = $this->helper->SalesChannel()->getStorefrontSalesChannel();

        $dataNL = [[
            'id' => self::CUSTOMER_ID_NL,
            'salesChannelId' => $salesChannel->getId(),
            'groupId' => $salesChannel->getCustomerGroupId(),
            'defaultPaymentMethodId' => $this->helper->PaymentMethod()->getInvoicePaymentMethod()->getId(),
            'defaultBillingAddress' => [
                'id' => self::ADDRESS_ID_NL,
                'salutationId' => $this->helper->Customer()->getNotSpecifiedSalutation()->getId(),
                'firstName' => 'Mollie NL',
                'lastName' => 'Test',
                'zipcode' => '1015 CW',
                'street' => 'Keizersgracht 126',
                'city' => 'Amsterdam',
                'countryId' => $this->helper->SalesChannel()->getCountry('NL')->getId(),
            ],
            'defaultShippingAddressId' => self::ADDRESS_ID_NL,
            'salutationId' => $this->helper->Customer()->getNotSpecifiedSalutation()->getId(),
            'customerNumber' => '1122',
            'firstName' => 'Mollie NL',
            'lastName' => 'Test',
            'email' => 'test@mollie.nl',
            'password' => 'molliemollie'
        ]];

        $this->customerRepository->upsert($dataNL, Context::createDefaultContext());

    }

}
