<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\WebhookBuilder;

use Kiener\MolliePayments\Service\WebhookBuilder\WebhookBuilder;
use MolliePayments\Tests\Fakes\FakePluginSettings;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\Router\RoutingDetector;
use MolliePayments\Tests\Fakes\FakeRouter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RequestStack;


class WebhookBuilderTest extends TestCase
{

    /**
     *
     */
    public function setUp(): void
    {
        putenv('MOLLIE_SHOP_DOMAIN=');
    }

    /**
     *
     */
    public function tearDown(): void
    {
        putenv('MOLLIE_SHOP_DOMAIN=');
    }

    /**
     * This test verifies that our router is correctly
     * used and its generated URL is being returned correctly.
     */
    public function testRouterIsUsed(): void
    {
        $fakeRouter = new FakeRouter('https://local.mollie.shop/notify/123');
        $builder = new RoutingBuilder($fakeRouter, new RoutingDetector(new RequestStack()));
        $builder = new WebhookBuilder($fakeRouter, new FakePluginSettings(''));


        $url = $builder->buildWebhookURL('-');

        $this->assertEquals('https://local.mollie.shop/notify/123', $url);
    }

    /**
     * This test verifies that we use our custom domain instead of
     * the dynamic shop domain if it has been set as ENV variable.
     * Our generated URL should be the one from the router, but the
     * domain will be replaced with our custom domain.
     */
    public function testCustomDomain(): void
    {
        # prepare our fake server data
        # assign a current domain to replace.
        # also configure our environment variable
        $fakeSettings = new FakePluginSettings('123.eu.ngrok.io');

        $fakeRouter = new FakeRouter('https://local.mollie.shop/notify/123');
        $builder = new WebhookBuilder($fakeRouter, $fakeSettings);
        $builder = new RoutingBuilder($fakeRouter, new RoutingDetector(new RequestStack()));

        $url = $builder->buildWebhookURL('-');

        $this->assertEquals('https://123.eu.ngrok.io/notify/123', $url);
    }

}
