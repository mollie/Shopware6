<?php declare(strict_types=1);

namespace Kiener\MolliePayments;

use Exception;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class MolliePayments extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Load dependency injection
        $this->container = $container;

        // Load services
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));

        try {
            $loader->load('services.xml');
            $loader->load('payment_handlers.xml');
        } catch (Exception $e) {
            // @todo Handle Exception
        }
    }

    public function boot(): void
    {
        parent::boot();
    }

    public function install(InstallContext $context) : void
    {
        parent::install($context);

        // Add custom fields
        $customFieldService = new CustomFieldService(
            $this->container,
            $this->container->get('custom_field_set.repository')
        );

        $customFieldService->addCustomFields($context->getContext());

        // Add payment methods
        $paymentMethodHelper = new PaymentMethodService(
            $this->container->get('payment_method.repository'),
            $this->container->get(PluginIdProvider::class),
            $this->container->get('system_config.repository'),
            get_class($this)
        );

        $paymentMethodHelper->addPaymentMethods($context->getContext());
    }

    public function uninstall(UninstallContext $context) : void
    {
        parent::uninstall($context);
    }

    public function activate(ActivateContext $context) : void
    {
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context) : void
    {
        parent::deactivate($context);
    }
}