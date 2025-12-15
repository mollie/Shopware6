import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();


const testDevices = [devices.getFirstDevice()];

const scenarioDummyBasket = new DummyBasketScenario(1);


context('Active Payment Methods', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            it('C3996: Mollie Payment Methods show TEST MODE @core', () => {

                devices.setDevice(device);

                const shopConfig = new ShopConfiguration();
                const pluginConfig = new PluginConfiguration();

                pluginConfig.setMollieFailureMode(true);

                configAction.configureEnvironment(shopConfig, pluginConfig);

                session.resetBrowserSession();

                scenarioDummyBasket.execute();

                paymentAction.showPaymentMethods();

                // yes we require test mode, but this is
                // the only chance to see if the plugin is being used, because
                // every merchant might have different payment methods ;)
                cy.contains('(Test mode)');
            })
        })
    })
})

context('Deprecated Payment Methods', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            it('C38332: ING Home Pay must not exist @core', () => {

                devices.setDevice(device);

                const shopConfig = new ShopConfiguration();
                const pluginConfig = new PluginConfiguration();

                pluginConfig.setMollieFailureMode(true);

                configAction.configureEnvironment(shopConfig, pluginConfig);

                session.resetBrowserSession();

                scenarioDummyBasket.execute();

                paymentAction.showPaymentMethods();

                cy.contains('ING Home').should('not.exist');
            })

            it('C38333: SEPA Direct Debit must not exist @core', () => {

                devices.setDevice(device);

                const shopConfig = new ShopConfiguration();
                const pluginConfig = new PluginConfiguration();

                pluginConfig.setMollieFailureMode(true);

                configAction.configureEnvironment(shopConfig, pluginConfig);

                session.resetBrowserSession();

                scenarioDummyBasket.execute();

                paymentAction.showPaymentMethods();

                cy.contains('SEPA Direct Deb').should('not.exist');
            })

        })
    })
})
