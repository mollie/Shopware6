<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Router;

use Mollie\Shopware\Component\Payment\Controller\PaymentMethodController;
use Mollie\Shopware\Component\Refund\Route\CancelRefundRoute;
use Mollie\Shopware\Component\Refund\Route\CreateRefundRoute;
use Mollie\Shopware\Component\Shipment\Route\CancelItemRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipItemRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipmentApiRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Component\Subscription\Controller\ApiController;
use Mollie\Shopware\Component\Subscription\Controller\RescueApiController;
use Mollie\Shopware\Component\Support\Controller\SupportController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

#[CoversClass(CreateRefundRoute::class)]
#[CoversClass(CancelRefundRoute::class)]
#[CoversClass(ShipOrderRoute::class)]
#[CoversClass(ShipItemRoute::class)]
#[CoversClass(CancelItemRoute::class)]
#[CoversClass(ShipmentApiRoute::class)]
#[CoversClass(ApiController::class)]
#[CoversClass(RescueApiController::class)]
#[CoversClass(PaymentMethodController::class)]
#[CoversClass(SupportController::class)]
final class AdminRoutePrivilegesTest extends TestCase
{
    /**
     * @param class-string $controller
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('privilegedRoutes')]
    public function testRouteRequiresPrivilege(string $controller, string $routeName, array $expectedPrivileges): void
    {
        $loader = new AttributeRouteControllerLoader();

        $route = $loader->load($controller)->get($routeName);

        $this->assertNotNull($route);
        $this->assertSame($expectedPrivileges, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return array<string, array{class-string, string, list<string>}>
     */
    public static function privilegedRoutes(): array
    {
        return [
            'refund' => [CreateRefundRoute::class, 'api.action.mollie.refund', ['mollie_refund_manager:create']],
            'refund cancel' => [CancelRefundRoute::class, 'api.action.mollie.refund.cancel', ['mollie_refund_manager:delete']],
            'ship order' => [ShipOrderRoute::class, 'api.action.mollie.ship.order', ['order:update']],
            'ship item' => [ShipItemRoute::class, 'api.action.mollie.ship.item', ['order:update']],
            'cancel item' => [CancelItemRoute::class, 'api.action.mollie.cancel.item', ['order:update']],
            'erp ship order' => [ShipmentApiRoute::class, 'api.mollie.ship.order', ['order:update']],
            'erp ship order batch' => [ShipmentApiRoute::class, 'api.mollie.ship.order.batch', ['order:update']],
            'erp ship item' => [ShipmentApiRoute::class, 'api.mollie.ship.item', ['order:update']],
            'subscription pause' => [ApiController::class, 'api.action.mollie.subscription.pause', ['mollie_subscription:update']],
            'subscription resume' => [ApiController::class, 'api.action.mollie.subscription.resume', ['mollie_subscription:update']],
            'subscription skip' => [ApiController::class, 'api.action.mollie.subscription.skip', ['mollie_subscription:update']],
            'subscription cancel' => [ApiController::class, 'api.action.mollie.subscription.cancel', ['mollie_subscription_custom:cancel']],
            'subscription any action' => [ApiController::class, 'api.action.mollie.subscription.changeState', ['mollie_subscription:update', 'mollie_subscription_custom:cancel']],
            'subscription edit' => [ApiController::class, 'api.action.mollie.subscription.edit', ['mollie_subscription:update']],
            'subscription rescue cancel' => [RescueApiController::class, 'api.action.mollie.subscription.cancel_by_customer', ['mollie_subscription_custom:cancel']],
            'update payment methods' => [PaymentMethodController::class, 'api.mollie.payment-method.update-methods', ['payment_method:update']],
            'support request' => [SupportController::class, 'api.action.mollie.support.request', ['system_config:read']],
        ];
    }
}
