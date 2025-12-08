import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const checkout = new CheckoutAction();
const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();


const testDevices = [devices.getFirstDevice()];

const scenarioDummyBasket = new DummyBasketScenario(1);


function beforeEach(device) {
    cy.wrap(null).then(() => {
        devices.setDevice(device);

        const shopConfig = new ShopConfiguration();
        const pluginConfig = new PluginConfiguration();

        pluginConfig.setMollieFailureMode(true);

        configAction.configureEnvironment(shopConfig, pluginConfig);

        session.resetBrowserSession();
    });
}


describe('POS Terminals', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            it('C1341121: Terminals List on payment selection page', () => {

                beforeEach(device);

                scenarioDummyBasket.execute();

                paymentAction.showPaymentMethods();

                paymentAction.selectPaymentMethod('POS Terminal');
                paymentAction.selectPosTerminal();
            })

            it('C1504402: POS Terminal Checkout redirects to custom waiting screen', () => {

                beforeEach(device);

                scenarioDummyBasket.execute();

                paymentAction.showPaymentMethods();

                paymentAction.selectPaymentMethod('POS Terminal');
                paymentAction.selectPosTerminal();

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                cy.url().should('include', '/mollie/pos/checkout?sw=');

                cy.contains('Follow the instructions on the terminal');
            })
        })
    })
})
