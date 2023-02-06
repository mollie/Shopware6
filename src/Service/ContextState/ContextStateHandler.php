<?php

namespace Kiener\MolliePayments\Service\ContextState;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ContextStateHandler
{

    /**
     * @var string
     */
    private $identifier;


    /**
     * @param string $identifier
     */
    public function __construct(string $identifier)
    {
        $this->identifier = 'mollie_' . $identifier;
    }


    /**
     * @param SalesChannelContext $context
     * @return bool
     */
    public function hasSnapshot(SalesChannelContext $context): bool
    {
        return $context->hasExtension($this->identifier);
    }

    /**
     * @param SalesChannelContext $context
     * @return null|mixed
     */
    public function getSnapshot(SalesChannelContext $context)
    {
        $ext = $context->getExtension($this->identifier);

        if (!$ext instanceof Struct) {
            return null;
        }

        return $ext->getVars()[0];
    }

    /**
     * @param mixed $data
     * @param SalesChannelContext $context
     * @return void
     */
    public function saveSnapshot($data, SalesChannelContext $context): void
    {
        $context->addArrayExtension($this->identifier, [$data]);
    }
}
