<?php

namespace Kiener\MolliePayments\Service\Logger\Services;


class IPAnonymizer
{

    /**
     * @var string
     */
    private $placeholder;


    /**
     * @param string $placeholder
     */
    public function __construct($placeholder)
    {
        $this->placeholder = $placeholder;
    }

    /**
     * Gets an anonymous version of the
     * provided IP address string.
     * The anonymous IP will end with a 0.
     *
     * @param string $ip
     * @return string
     */
    public function anonymize($ip)
    {
        $ip = trim($ip);

        # return an empty string and fail safe
        # for our logger if the IP address is not even valid
        if (!$this->isValidIP($ip)) {
            return '';
        }

        $ipOctets = explode('.', $ip);

        return $ipOctets[0] . '.' . $ipOctets[1] . '.' . $ipOctets[2] . '.' . $this->placeholder;
    }

    /**
     * Gets if the provided IP is even a valid IP address.
     *
     * @param string $ip
     * @return bool
     */
    private function isValidIP($ip)
    {
        return (filter_var($ip, FILTER_VALIDATE_IP) !== false);
    }
}
