<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Payment\Token\PaymentTokenRepositoryInterface;

final class FakePaymentTokenRepository implements PaymentTokenRepositoryInterface
{
    /** @var list<string> */
    private array $consumedTokens = [];

    /** @var list<string> */
    private array $checkedTokens = [];

    public function withConsumedToken(string $paymentToken): void
    {
        $this->consumedTokens[] = $paymentToken;
    }

    public function isConsumed(string $paymentToken): bool
    {
        $this->checkedTokens[] = $paymentToken;

        return in_array($paymentToken, $this->consumedTokens, true);
    }

    /**
     * @return list<string>
     */
    public function getCheckedTokens(): array
    {
        return $this->checkedTokens;
    }
}
