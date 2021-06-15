<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;

class ShopwareReturnUrlExtractor
{
    /**
     * @var TokenFactoryInterfaceV2
     */
    private $tokenFactory;

    public function __construct(TokenFactoryInterfaceV2 $tokenFactory)
    {

        $this->tokenFactory = $tokenFactory;
    }

    public function extractSuccessURL(string $transactionReturnUrl): string
    {
        parse_str(parse_url($transactionReturnUrl, PHP_URL_QUERY), $queryResult);

        if (!isset($queryResult['_sw_payment_token'])) {
            return $transactionReturnUrl;
        }

        $token = $this->tokenFactory->parseToken($queryResult['_sw_payment_token']);

        return $token->getFinishUrl();
    }
}
