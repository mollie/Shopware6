<?php

namespace Kiener\MolliePayments\Service\Logger\Services;


class URLAnonymizer implements URLAnonymizerInterface
{

    /**
     * @param string $url
     * @return string
     */
    public function anonymize($url)
    {
        # we do not want to save the used tokens in our log file
        # also, those one time tokens are soooooo long. it's just annoying and fills disk space ;)
        if (strpos($url, '/payment/finalize-transaction') !== false) {
            $url = substr($url, 0, 80);
        }

        return (string)$url;
    }

}
