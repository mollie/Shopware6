<?php declare(strict_types=1);

namespace Kiener\MolliePayments;

use Kiener\MolliePayments\Compatibility\DependencyLoader;
use Kiener\MolliePayments\Service\ApplePayDirect\ApplePayDomainVerificationService;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MolliePayments extends Plugin
{

    const PLUGIN_VERSION = '2.2.2';

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->container = $container;

        # load the dependencies that are compatible
        # with our current shopware version
        $loader = new DependencyLoader($container);
        $loader->loadServices();
    }

    public function boot(): void
    {
        parent::boot();
    }

    public function install(InstallContext $context): void
    {
        parent::install($context);

        /** @var EntityRepositoryInterface $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field_set.repository');

        // Add custom fields
        $customFieldService = new CustomFieldService(
            $this->container,
            $customFieldRepository
        );

        $customFieldService->addCustomFields($context->getContext());
    }

    public function update(UpdateContext $context): void
    {
        parent::update($context);

        if ($context->getPlugin()->isActive() === true) {
            // Install and activate payment methods
            $this->installAndActivatePaymentMethods($context->getContext());

            // add domain verification
            /** @var ApplePayDomainVerificationService $domainVerificationService */
            $domainVerificationService = $this->container->get(ApplePayDomainVerificationService::class);
            $domainVerificationService->downloadDomainAssociationFile();
        }
    }

    public function postInstall(InstallContext $context): void
    {
        parent::postInstall($context);
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);
    }

    public function activate(ActivateContext $context): void
    {
        parent::activate($context);

        // Install and activate payment methods
        $this->installAndActivatePaymentMethods($context->getContext());

        // Add domain verification
        /** @var ApplePayDomainVerificationService $domainVerificationService */
        $domainVerificationService = $this->container->get(ApplePayDomainVerificationService::class);
        $domainVerificationService->downloadDomainAssociationFile();
    }

    public function deactivate(DeactivateContext $context): void
    {
        parent::deactivate($context);
    }

    /**
     * @param Context $context
     */
    private function installAndActivatePaymentMethods(Context $context): void
    {
        /** @var PaymentMethodService $paymentMethodService */
        $paymentMethodService = $this->container->get(PaymentMethodService::class);

        $paymentMethodService->installAndActivatePaymentMethods($context);
    }
}
