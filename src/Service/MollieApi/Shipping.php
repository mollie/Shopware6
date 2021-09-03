<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\LoggerService;

class Shipping
{
    /**
     * @var MollieApiFactory
     */
    private $clientFactory;

    /**
     * @var LoggerService
     */
    private $logger;

    public function __construct(
        MollieApiFactory $clientFactory,
        LoggerService $logger
    )
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
    }
}
