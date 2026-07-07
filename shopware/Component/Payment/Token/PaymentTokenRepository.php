<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Token;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\Hasher;

/**
 * Checks whether a Shopware finalize token has already been consumed, i.e. removed from the
 * `payment_token` table when finalize ran.
 *
 * This only exists because of Shopware 6.5, which ships JWTFactoryV2 (storing the token as a
 * shortened sha256 hash and deleting the row on finalize) but not the newer PaymentTokenLifecycle
 * service. Once the minimum supported Shopware version provides PaymentTokenLifecycle, this
 * repository can be removed and replaced by that service.
 */
final class PaymentTokenRepository implements PaymentTokenRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function isConsumed(string $paymentToken): bool
    {
        // Shopware >= 6.8 keys the row by JWT id instead of the hashed token, so this lookup would
        // never match; report the token as still valid and let the caller's finalize catch handle it.
        if (Feature::isActive('v6.8.0.0')) {
            return false;
        }

        $tokenKey = substr(Hasher::hash($paymentToken, 'sha256'), 0, 32);

        $result = $this->connection->fetchOne(
            'SELECT 1 FROM payment_token WHERE token = :token',
            ['token' => $tokenKey]
        );

        return $result === false;
    }
}
