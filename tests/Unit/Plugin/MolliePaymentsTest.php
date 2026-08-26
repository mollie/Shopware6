<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Plugin;

use Kiener\MolliePayments\MolliePayments;
use Mollie\Shopware\Component\Installer\MollieDataRemover;
use Mollie\Shopware\Unit\Plugin\Fake\FakeDataRemover;
use Mollie\Shopware\Unit\Plugin\Fake\FakeMigrationCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\Container;

/**
 * The uninstall is the one place in the plugin that can destroy merchant data, so what it does -
 * and above all what it must not do - is pinned here.
 */
#[CoversClass(MolliePayments::class)]
final class MolliePaymentsTest extends TestCase
{
    public function testInstallingThePluginRunsItsDatabaseMigrations(): void
    {
        $plugin = $this->plugin(new Container());
        $migrations = new FakeMigrationCollection();

        $plugin->install($this->installContext($plugin, $migrations));

        $this->assertSame(1, $migrations->getMigrateInPlaceCount());
    }

    /**
     * The merchant ticked "keep my data" in the uninstall dialog - nothing may be deleted then,
     * so old orders keep showing their Mollie payment.
     */
    public function testUninstallingKeepsEverythingWhenTheMerchantAskedToKeepTheirData(): void
    {
        $dataRemover = new FakeDataRemover();
        $plugin = $this->plugin($this->containerWith($dataRemover));

        $plugin->uninstall($this->uninstallContext($plugin, keepUserData: true));

        $this->assertSame(0, $dataRemover->getRemoveCount());
    }

    public function testUninstallingRemovesTheDataWhenTheMerchantAskedForIt(): void
    {
        $dataRemover = new FakeDataRemover();
        $plugin = $this->plugin($this->containerWith($dataRemover));

        $plugin->uninstall($this->uninstallContext($plugin, keepUserData: false));

        $this->assertSame(1, $dataRemover->getRemoveCount());
    }

    /**
     * Uninstalling an inactive plugin never loaded the plugin's services, so there is nothing
     * wired up to remove. That must end in a no-op, not in a fatal.
     */
    public function testUninstallingAnInactivePluginRemovesNothing(): void
    {
        $dataRemover = new FakeDataRemover();
        // An inactive plugin never loaded its services, so the data remover is not in the container.
        $plugin = $this->plugin(new Container());

        $plugin->uninstall($this->uninstallContext($plugin, keepUserData: false));

        $this->assertSame(0, $dataRemover->getRemoveCount());
    }

    private function plugin(Container $container, bool $active = false): MolliePayments
    {
        $plugin = new MolliePayments($active, '/plugins/MolliePayments');
        $plugin->setContainer($container);

        return $plugin;
    }

    private function containerWith(FakeDataRemover $dataRemover): Container
    {
        $container = new Container();
        $container->set(MollieDataRemover::class, new MollieDataRemover([$dataRemover], new NullLogger()));

        return $container;
    }

    private function installContext(MolliePayments $plugin, FakeMigrationCollection $migrations): InstallContext
    {
        return new InstallContext($plugin, Context::createDefaultContext(), '6.7.0.0', MolliePayments::PLUGIN_VERSION, $migrations);
    }

    private function uninstallContext(MolliePayments $plugin, bool $keepUserData): UninstallContext
    {
        return new UninstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.7.0.0',
            MolliePayments::PLUGIN_VERSION,
            new FakeMigrationCollection(),
            $keepUserData
        );
    }
}
