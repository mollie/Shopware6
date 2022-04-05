<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Checkout\Cart\Error\Error;

class SubscriptionCartNotice extends Error
{
    private const KEY = 'mollie-subscription-line-item-notice';

    /**
     * @var string
     */
    private $lineItemId;

    /**
     * @param string $lineItemId
     */
    public function __construct(string $lineItemId)
    {
        $this->lineItemId = $lineItemId;
        parent::__construct();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->lineItemId;
    }

    /**
     * @return string
     */
    public function getMessageKey(): string
    {
        return self::KEY;
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return self::LEVEL_NOTICE;
    }

    /**
     * @return bool
     */
    public function blockOrder(): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    public function getParameters(): array
    {
        return [ 'lineItemId' => $this->lineItemId ];
    }
}
