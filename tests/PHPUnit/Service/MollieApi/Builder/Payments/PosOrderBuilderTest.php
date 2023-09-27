<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder\Payments;

use Kiener\MolliePayments\Handler\Method\PosPayment;
use MolliePayments\Tests\Fakes\FakeContainer;
use MolliePayments\Tests\Service\MollieApi\Builder\AbstractMollieOrderBuilder;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class PosOrderBuilderTest extends AbstractMollieOrderBuilder
{

    /**
     * This test verifies that the identifier of our payment is correct.
     * This is required for the functionality with Mollie.
     * @return void
     */
    public function testMollieIdentifier(): void
    {
        $this->assertEquals('pointofsale', PosPayment::PAYMENT_METHOD_NAME);
    }

    /**
     * This test verifies that the default name of our payment is correct.
     * This is also used in Cypress tests.
     * @return void
     */
    public function testDefaultName(): void
    {
        $this->assertEquals('POS Terminal', PosPayment::PAYMENT_METHOD_DESCRIPTION);
    }

    /**
     * This test verifies that the terminal ID is extracted from the customers
     * custom fields, if it is set.
     * @return void
     */
    public function testTerminalIsExtractedFromCustomer(): void
    {
        $pos = new PosPayment(
            $this->loggerService,
            new FakeContainer()
        );

        $customer = new CustomerEntity();

        $customer->setCustomFields(
            [
                'mollie_payments' => [
                    'preferred_pos_terminal' => 'term_123'
                ]
            ]
        );

        $pos->processPaymentMethodSpecificParameters(
            [],
            new OrderEntity(),
            $this->salesChannelContext,
            $customer
        );

        $terminalID = $pos->getTerminalId();

        $this->assertEquals('term_123', $terminalID);
    }

}
