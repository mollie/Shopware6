<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\Method\ApplePayPayment;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Repository\PaymentMethodRepository;
use Mollie\Shopware\Repository\PaymentMethodRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class ApplePayDirectEnabledRoute extends AbstractApplePayDirectEnabledRoute
{
    public function __construct(
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService
    ) {
    }

    public function getDecorated(): AbstractApplePayDirectEnabledRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.apple-pay.enabled', path: '/store-api/mollie/applepay/enabled', methods: ['GET'])]
    public function getEnabled(SalesChannelContext $salesChannelContext): ApplePayDirectEnabledResponse
    {
        $enabled = false;
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $id = $this->paymentMethodRepository->getIdByPaymentHandler(ApplePayPayment::class, $salesChannelId, $salesChannelContext->getContext());

        if ($id !== null) {
            $applePaySettings = $this->settingsService->getApplePaySettings($salesChannelContext->getSalesChannelId());
            $enabled = $applePaySettings->isApplePayDirectEnabled();
        }

        return new ApplePayDirectEnabledResponse($enabled, $id);
    }
}
