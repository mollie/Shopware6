<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;

final class FakeTokenFactory implements TokenFactoryInterfaceV2
{
    /** @var list<TokenStruct> */
    private array $generatedFor = [];

    public function __construct(private string $token = 'generated-token')
    {
    }

    public function generateToken(TokenStruct $tokenStruct): string
    {
        $this->generatedFor[] = $tokenStruct;

        return $this->token;
    }

    public function parseToken(string $token): TokenStruct
    {
        return new TokenStruct($token);
    }

    public function invalidateToken(string $tokenId): bool
    {
        return true;
    }

    public function getLastTokenStruct(): TokenStruct
    {
        $last = end($this->generatedFor);

        if ($last === false) {
            throw new \RuntimeException('No token has been generated.');
        }

        return $last;
    }
}
