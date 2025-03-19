<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Storefront;

use Psr\Container\ContainerInterface;
use Shopware\Storefront\Controller\StorefrontController;

abstract class AbstractStoreFrontController extends StorefrontController
{
    /**
     * Since Shopware 6.6.0.0 twig must be set with setTwig method, prior the method does not exists
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $container = parent::setContainer($container);
        /** @phpstan-ignore-next-line  */
        if ($container instanceof ContainerInterface && method_exists($this, 'setTwig')) {
            $this->setTwig($container->get('twig'));
        }

        return $container;
    }
}
