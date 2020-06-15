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
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
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
    }

    public function update(UpdateContext $context): void
    {
        parent::update($context);
    }

    public function postInstall(InstallContext $context): void
    {
        parent::postInstall($context);

    }

    public function uninstall(UninstallContext $context) : void
    {
        parent::uninstall($context);
    }

    public function activate(ActivateContext $context) : void
    {
        parent::activate($context);

        /** @var PaymentMethodService $paymentMethodHelper */
        $paymentMethodHelper = $this->container->get('Kiener\MolliePayments\Service\PaymentMethodService');

        // Add payment methods
        $paymentMethodHelper
            ->setClassName(get_class($this))
            ->addPaymentMethods($context->getContext());
    }

    public function deactivate(DeactivateContext $context) : void
    {
        parent::deactivate($context);
    }
}