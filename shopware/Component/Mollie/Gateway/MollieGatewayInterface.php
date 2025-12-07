<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Profile;
use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

interface MollieGatewayInterface
{
    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment;

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment;

    public function getCurrentProfile(?string $salesChannelId = null): Profile;

    public function createCustomer(CustomerEntity $customer,string $salesChannelId): Customer;

    public function listMandates(string $mollieCustomerId, string $salesChannelId): MandateCollection;

    public function listTerminals(string $salesChannelId): TerminalCollection;
}
