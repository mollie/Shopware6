<?php

namespace Kiener\MolliePayments\Service;

use Symfony\Component\Routing\RouterInterface;

class ShopService
{
    /** @var RouterInterface */
    private $router;

    public function __construct(
        RouterInterface $router
    ) {
        $this->router = $router;
    }

    /**
     * Returns the shop url without http(s):// and/or trailing slash.
     *
     * @param bool $stripHttp
     *
     * @return string
     */
    public function getShopUrl($stripHttp = true): string
    {
        /** @var string $shopUrl */
        $shopUrl = $this->router->generate(
            'frontend.home.page',
            [],
            $this->router::ABSOLUTE_URL
        );

        if ($stripHttp === true) {
            $shopUrl = str_ireplace('http://', '', $shopUrl);
            $shopUrl = str_ireplace('https://', '', $shopUrl);
        }

        if (substr($shopUrl, -1) === '/') {
            $shopUrl = substr($shopUrl, 0, -1);
        }

        return $shopUrl;
    }
}
