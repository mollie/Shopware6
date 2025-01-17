<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Exceptions;

class ApplePayDirectDomainNotInAllowListException extends \Exception
{
    public function __construct(string $url)
    {
        parent::__construct(sprintf('The given URL %s is not in the Apple Pay Direct domain allow list.', $url));
    }
}
