<?php

namespace Kiener\MolliePayments\Service\Logger\Services;

interface URLAnonymizerInterface
{
    /**
     * @param string $url
     * @return string
     */
    public function anonymize($url);
}
