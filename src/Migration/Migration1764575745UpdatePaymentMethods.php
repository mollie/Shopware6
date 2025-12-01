<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1764575745UpdatePaymentMethods extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764575745;
    }

    public function update(Connection $connection): void
    {
        $replacementClasses = [
            'Kiener\MolliePayments\Handler\Method\AlmaPayment' => \Mollie\Shopware\Component\Payment\Method\AlmaPayment::class,
            'Kiener\MolliePayments\Handler\Method\BanContactPayment' => \Mollie\Shopware\Component\Payment\Method\BanContactPayment::class,
            'Kiener\MolliePayments\Handler\Method\BelfiusPayment' => \Mollie\Shopware\Component\Payment\Method\BelfiusPayment::class,
            'Kiener\MolliePayments\Handler\Method\BilliePayment' => \Mollie\Shopware\Component\Payment\Method\BilliePayment::class,
            'Kiener\MolliePayments\Handler\Method\BizumPayment' => \Mollie\Shopware\Component\Payment\Method\BizumPayment::class,
            'Kiener\MolliePayments\Handler\Method\BlikPayment' => \Mollie\Shopware\Component\Payment\Method\BlikPayment::class,
            'Kiener\MolliePayments\Handler\Method\CreditCardPayment' => \Mollie\Shopware\Component\Payment\Method\CardPayment::class,
            'Kiener\MolliePayments\Handler\Method\DirectDebitPayment' => \Mollie\Shopware\Component\Payment\Method\DirectDebitPayment::class,
            'Kiener\MolliePayments\Handler\Method\EpsPayment' => \Mollie\Shopware\Component\Payment\Method\EpsPayment::class,
            'Kiener\MolliePayments\Handler\Method\GiftCardPayment' => \Mollie\Shopware\Component\Payment\Method\GiftCardPayment::class,
            'Kiener\MolliePayments\Handler\Method\iDealPayment' => \Mollie\Shopware\Component\Payment\Method\IdealPayment::class,
            'Kiener\MolliePayments\Handler\Method\In3Payment' => \Mollie\Shopware\Component\Payment\Method\In3Payment::class,
            'Kiener\MolliePayments\Handler\Method\KbcPayment' => \Mollie\Shopware\Component\Payment\Method\KbcPayment::class,
            'Kiener\MolliePayments\Handler\Method\KlarnaOnePayment' => \Mollie\Shopware\Component\Payment\Method\KlarnaPayment::class,
            'Kiener\MolliePayments\Handler\Method\MbWayPayment' => \Mollie\Shopware\Component\Payment\Method\MbWayPayment::class,
            'Kiener\MolliePayments\Handler\Method\MultibancoPayment' => \Mollie\Shopware\Component\Payment\Method\MultiBancoPayment::class,
            'Kiener\MolliePayments\Handler\Method\MyBankPayment' => \Mollie\Shopware\Component\Payment\Method\MyBankPayment::class,
            'Kiener\MolliePayments\Handler\Method\PayByBankPayment' => \Mollie\Shopware\Component\Payment\Method\PayByBankPayment::class,
            'Kiener\MolliePayments\Handler\Method\PayPalPayment' => \Mollie\Shopware\Component\Payment\Method\PayPalPayment::class,
            'Kiener\MolliePayments\Handler\Method\Przelewy24Payment' => \Mollie\Shopware\Component\Payment\Method\Przelewy24Payment::class,
            'Kiener\MolliePayments\Handler\Method\RivertyPayment' => \Mollie\Shopware\Component\Payment\Method\RivertyPayment::class,
            'Kiener\MolliePayments\Handler\Method\SatispayPayment' => \Mollie\Shopware\Component\Payment\Method\SatisPayPayment::class,
            'Kiener\MolliePayments\Handler\Method\SwishPayment' => \Mollie\Shopware\Component\Payment\Method\SwishPayment::class,
            'Kiener\MolliePayments\Handler\Method\TrustlyPayment' => \Mollie\Shopware\Component\Payment\Method\TrustlyPayment::class,
            'Kiener\MolliePayments\Handler\Method\TwintPayment' => \Mollie\Shopware\Component\Payment\Method\TwintPayment::class,
            'Kiener\MolliePayments\Handler\Method\PayconiqPayment' => \Mollie\Shopware\Component\Payment\Method\PayconiqPayment::class,
        ];
        foreach ($replacementClasses as $class => $replacementClass) {
            $replacementClass = addslashes($replacementClass);
            $class = addslashes($class);
            $sql = <<<SQL
UPDATE payment_method SET handler_identifier = '{$replacementClass}' WHERE handler_identifier = '{$class}';
SQL;

            $connection->executeStatement($sql);
        }
    }
}
